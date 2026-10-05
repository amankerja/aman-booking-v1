<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Templates\BusinessTemplateInstaller;
use App\Domain\Business\Templates\BusinessTemplateRegistry;
use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Display the 8-step onboarding wizard.
     */
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [
            'completed' => (bool) $business->published_at,
            'current_step' => $business->published_at ? 8 : 1,
            'template_id' => null,
        ];

        // Format services
        $services = $business->services()
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'description' => $s->description,
                'price_idr' => (float) $s->price_idr,
                'duration_minutes' => (int) $s->duration_minutes,
                'buffer_after' => (int) $s->buffer_after,
                'capacity' => (int) $s->capacity,
                'is_active' => (bool) $s->is_active,
                'is_featured' => (bool) $s->is_featured,
            ]);

        // Format resources
        $resources = $business->resources()
            ->whereNull('archived_at')
            ->with('resourceType')
            ->orderBy('id')
            ->get()
            ->map(fn (Resource $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'capacity' => (int) $r->capacity,
                'state' => $r->state,
                'type_code' => $r->resourceType ? $r->resourceType->code : 'STAFF',
                'type_name' => $r->resourceType ? $r->resourceType->name : 'Staff',
            ]);

        // Format operating hours
        $hours = $business->hours()
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (BusinessHour $h) => [
                'id' => $h->id,
                'day_of_week' => (int) $h->day_of_week,
                'is_open' => (bool) $h->is_open,
                'open_time' => substr((string) $h->open_time, 0, 5),
                'close_time' => substr((string) $h->close_time, 0, 5),
                'breaks' => is_array($h->breaks) ? $h->breaks : [],
            ]);

        return Inertia::render('Owner/Onboarding/Index', [
            'business' => [
                'id' => $business->id,
                'uuid' => $business->uuid,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => $business->logo_url,
                'whatsapp' => $business->whatsapp ?? '',
                'phone' => $business->phone ?? '',
                'email' => $business->email ?? '',
                'address' => $business->address ?? '',
                'timezone' => $business->timezone ?? 'Asia/Jakarta',
                'description' => $business->description ?? '',
                'is_published' => (bool) $business->published_at,
                'published_at' => $business->published_at ? Carbon::parse($business->published_at)->format('Y-m-d H:i:s') : null,
                'public_url' => url('/'.$business->slug),
            ],
            'templates' => array_values(BusinessTemplateRegistry::all()),
            'onboarding' => $onboarding,
            'services' => $services,
            'resources' => $resources,
            'hours' => $hours,
            'landing' => $settings['landing'] ?? null,
            'workflow' => $settings['workflow'] ?? null,
        ]);
    }

    /**
     * Step 1: Save Business Profile.
     */
    public function saveProfile(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'timezone' => ['required', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['current_step'] = max((int) ($onboarding['current_step'] ?? 1), 2);
        $settings['onboarding'] = $onboarding;

        $business->update([
            'name' => $validated['name'],
            'whatsapp' => $validated['whatsapp'],
            'email' => $validated['email'],
            'address' => $validated['address'],
            'timezone' => $validated['timezone'],
            'description' => $validated['description'],
            'settings' => $settings,
        ]);

        Audit::record([
            'action' => 'onboarding.step1_completed',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Profil usaha berhasil disimpan! Silakan pilih template bisnis Anda.');
    }

    /**
     * Step 2: Install Business Template (PRD 168-170, 192).
     */
    public function installTemplate(Request $request, BusinessTemplateInstaller $installer): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'template_id' => ['required', 'string', Rule::in(['barbershop', 'salon', 'spa', 'sports_court', 'rental', 'custom'])],
            'customization' => ['nullable', 'array'],
        ]);

        $customization = is_array($validated['customization'] ?? null) ? $validated['customization'] : [];

        $installer->install($tenant, $business, $validated['template_id'], $customization);

        Audit::record([
            'action' => 'onboarding.template_installed',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'meta' => [
                'template_id' => $validated['template_id'],
                'customization' => $customization,
            ],
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Template berhasil diinstal sebagai DRAFT! Silakan periksa daftar layanan Anda.');
    }

    /**
     * Step 3: Update Services list.
     */
    public function updateServices(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.price_idr' => ['required', 'numeric', 'min:0'],
            'services.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'services.*.buffer_after' => ['nullable', 'integer', 'min:0', 'max:120'],
            'services.*.is_active' => ['nullable', 'boolean'],
        ]);

        foreach ($validated['services'] as $srvData) {
            Service::where('tenant_id', $tenant->id)
                ->where('business_id', $business->id)
                ->where('id', $srvData['id'])
                ->update([
                    'name' => $srvData['name'],
                    'price_idr' => $srvData['price_idr'],
                    'duration_minutes' => $srvData['duration_minutes'],
                    'buffer_after' => $srvData['buffer_after'] ?? 0,
                    'is_active' => $srvData['is_active'] ?? true,
                ]);
        }

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['current_step'] = max((int) ($onboarding['current_step'] ?? 1), 4);
        $settings['onboarding'] = $onboarding;
        $business->update(['settings' => $settings]);

        return redirect()->back()->with('success', 'Layanan berhasil diperbarui. Silakan atur resource / staf Anda.');
    }

    /**
     * Step 4: Update Resources list.
     */
    public function updateResources(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'resources' => ['required', 'array', 'min:1'],
            'resources.*.id' => ['required', 'integer'],
            'resources.*.name' => ['required', 'string', 'max:150'],
            'resources.*.capacity' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['resources'] as $resData) {
            Resource::where('tenant_id', $tenant->id)
                ->where('business_id', $business->id)
                ->where('id', $resData['id'])
                ->update([
                    'name' => $resData['name'],
                    'capacity' => $resData['capacity'],
                ]);
        }

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['current_step'] = max((int) ($onboarding['current_step'] ?? 1), 5);
        $settings['onboarding'] = $onboarding;
        $business->update(['settings' => $settings]);

        return redirect()->back()->with('success', 'Resource & staf berhasil diperbarui. Sekarang atur jadwal operasional.');
    }

    /**
     * Step 5: Update Schedule / Operating Hours.
     */
    public function updateSchedule(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.is_open' => ['required', 'boolean'],
            'hours.*.open_time' => ['required', 'string'],
            'hours.*.close_time' => ['required', 'string'],
        ]);

        foreach ($validated['hours'] as $hourData) {
            $openTime = $hourData['open_time'];
            $closeTime = $hourData['close_time'];
            if (strlen($openTime) === 5) {
                $openTime .= ':00';
            }
            if (strlen($closeTime) === 5) {
                $closeTime .= ':00';
            }

            BusinessHour::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'business_id' => $business->id,
                    'day_of_week' => $hourData['day_of_week'],
                ],
                [
                    'is_open' => $hourData['is_open'],
                    'open_time' => $openTime,
                    'close_time' => $closeTime,
                ]
            );
        }

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['current_step'] = max((int) ($onboarding['current_step'] ?? 1), 6);
        $settings['onboarding'] = $onboarding;
        $business->update(['settings' => $settings]);

        return redirect()->back()->with('success', 'Jadwal operasional berhasil disimpan. Lanjutkan ke alur workflow & notifikasi.');
    }

    /**
     * Step 6: Update Workflow & Notifications.
     */
    public function updateWorkflow(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['current_step'] = max((int) ($onboarding['current_step'] ?? 1), 7);
        $settings['onboarding'] = $onboarding;
        $business->update(['settings' => $settings]);

        return redirect()->back()->with('success', 'Pengaturan workflow & notifikasi telah terverifikasi. Silakan tinjau seluruh data sebelum live.');
    }

    /**
     * Step 8: Go Live & Publish Business (PRD 48, 168).
     */
    public function publish(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $settings = is_array($business->settings) ? $business->settings : [];
        $onboarding = $settings['onboarding'] ?? [];
        $onboarding['completed'] = true;
        $onboarding['completed_at'] = now()->toIso8601String();
        $onboarding['current_step'] = 8;
        $settings['onboarding'] = $onboarding;

        $business->update([
            'published_at' => now(),
            'settings' => $settings,
        ]);

        Audit::record([
            'action' => 'business.published',
            'entity_type' => 'Business',
            'entity_id' => $business->id,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Selamat! Website & link booking online Anda resmi LIVE dan siap menerima pelanggan!');
    }

    /**
     * Reset draft template (PRD 171: Business Type Can Change).
     */
    public function reset(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        // Delete previous template services & resources
        $services = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->get();

        foreach ($services as $srv) {
            $rules = is_array($srv->rules) ? $srv->rules : [];
            if (($rules['origin'] ?? '') === 'SYSTEM_TEMPLATE') {
                $srv->delete();
            }
        }

        $resources = Resource::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->get();

        foreach ($resources as $res) {
            $meta = is_array($res->metadata) ? $res->metadata : [];
            if (($meta['origin'] ?? '') === 'SYSTEM_TEMPLATE') {
                $res->delete();
            }
        }

        $settings = is_array($business->settings) ? $business->settings : [];
        $settings['onboarding'] = [
            'completed' => false,
            'current_step' => 2,
            'template_id' => null,
        ];

        $business->update([
            'settings' => $settings,
        ]);

        return redirect()->back()->with('success', 'Data draft template telah direset. Silakan pilih template baru.');
    }
}
