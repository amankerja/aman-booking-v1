<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Subscription\Exceptions\PlanLimitReachedException;
use App\Domain\Subscription\Services\LimitEnforcer;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Services\ImageOptimizationService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function __construct(
        protected LimitEnforcer $limitEnforcer
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $search = $request->string('search')->trim()->value();
        $categoryId = $request->input('category_id');
        $status = $request->string('status', 'ALL')->value(); // ALL, ACTIVE, ARCHIVED

        $query = Service::where('business_id', $business->id)
            ->with(['category', 'variants', 'addons'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status === 'ACTIVE') {
            $query->whereNull('archived_at')->where('is_active', true);
        } elseif ($status === 'ARCHIVED') {
            $query->whereNotNull('archived_at');
        }

        $services = $query->get()->map(function (Service $s) {
            return [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'name' => $s->name,
                'slug' => $s->slug,
                'description' => $s->description,
                'image_url' => $s->image_url,
                'price_idr' => (float) $s->price_idr,
                'duration_type' => $s->duration_type,
                'duration_minutes' => $s->duration_minutes,
                'duration_rule' => $s->duration_rule,
                'buffer_before' => $s->buffer_before,
                'buffer_after' => $s->buffer_after,
                'total_duration' => $s->total_duration,
                'capacity' => $s->capacity,
                'is_active' => (bool) $s->is_active,
                'is_featured' => (bool) $s->is_featured,
                'is_archived' => $s->is_archived,
                'archived_at' => $s->archived_at?->toIso8601String(),
                'category' => $s->category ? [
                    'id' => $s->category->id,
                    'name' => $s->category->name,
                ] : null,
                'variants_count' => $s->variants->count(),
                'addons_count' => $s->addons->count(),
                'variants' => $s->variants->map(fn (ServiceVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price_idr' => (float) $v->price_idr,
                    'duration_minutes' => $v->duration_minutes,
                    'is_active' => (bool) $v->is_active,
                ]),
                'addons' => $s->addons->map(fn (ServiceAddon $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price_idr' => (float) $a->price_idr,
                    'duration_minutes' => $a->duration_minutes,
                    'is_active' => (bool) $a->is_active,
                ]),
            ];
        });

        $categories = ServiceCategory::where('business_id', $business->id)
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'order']);

        $usage = $this->limitEnforcer->getUsage('services', $tenant);

        return Inertia::render('Owner/Services/Index', [
            'services' => $services,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'status' => $status,
            ],
            'usage' => $usage,
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
            ],
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $canCreate = $this->limitEnforcer->canCreate('services', $tenant);
        $usage = $this->limitEnforcer->getUsage('services', $tenant);

        $categories = ServiceCategory::where('business_id', $business->id)
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Services/Form', [
            'mode' => 'create',
            'service' => null,
            'categories' => $categories,
            'canCreate' => $canCreate,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        // Enforce Plan Limit
        try {
            $this->limitEnforcer->enforce('services', $tenant);
        } catch (PlanLimitReachedException $e) {
            return redirect()->back()->withErrors([
                'plan_limit' => $e->getMessage(),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('tenant_id', $tenant->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_idr' => ['required', 'numeric', 'min:0'],
            'duration_type' => ['required', 'string', Rule::in(['FIXED', 'PER_QUANTITY', 'PER_UNIT_SIZE', 'VARIABLE'])],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'duration_rule' => ['nullable', 'array'],
            'buffer_before' => ['nullable', 'integer', 'min:0', 'max:240'],
            'buffer_after' => ['nullable', 'integer', 'min:0', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'rules' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.price_idr' => ['required', 'numeric', 'min:0'],
            'variants.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'variants.*.order' => ['nullable', 'integer'],
            'variants.*.is_active' => ['boolean'],
            'addons' => ['nullable', 'array'],
            'addons.*.name' => ['required', 'string', 'max:255'],
            'addons.*.price_idr' => ['required', 'numeric', 'min:0'],
            'addons.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'addons.*.order' => ['nullable', 'integer'],
            'addons.*.is_active' => ['boolean'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = app(ImageOptimizationService::class)->optimizeAndStore(
                $request->file('image'),
                'services',
                'public',
                1600
            );
        }

        $slug = Str::slug($validated['name']);
        $existingCount = Service::where('business_id', $business->id)->where('slug', $slug)->count();
        if ($existingCount > 0) {
            $slug .= '-'.($existingCount + 1);
        }

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image_path' => $imagePath,
            'price_idr' => $validated['price_idr'],
            'duration_type' => $validated['duration_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'duration_rule' => $validated['duration_rule'] ?? null,
            'buffer_before' => $validated['buffer_before'] ?? 0,
            'buffer_after' => $validated['buffer_after'] ?? 0,
            'capacity' => $validated['capacity'],
            'is_active' => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
            'rules' => $validated['rules'] ?? null,
        ]);

        // Insert variants
        if (! empty($validated['variants'])) {
            foreach ($validated['variants'] as $vIndex => $vData) {
                ServiceVariant::create([
                    'tenant_id' => $tenant->id,
                    'service_id' => $service->id,
                    'name' => $vData['name'],
                    'price_idr' => $vData['price_idr'],
                    'duration_minutes' => $vData['duration_minutes'],
                    'order' => $vData['order'] ?? $vIndex,
                    'is_active' => $vData['is_active'] ?? true,
                ]);
            }
        }

        // Insert addons
        if (! empty($validated['addons'])) {
            foreach ($validated['addons'] as $aIndex => $aData) {
                ServiceAddon::create([
                    'tenant_id' => $tenant->id,
                    'service_id' => $service->id,
                    'name' => $aData['name'],
                    'price_idr' => $aData['price_idr'],
                    'duration_minutes' => $aData['duration_minutes'] ?? 0,
                    'order' => $aData['order'] ?? $aIndex,
                    'is_active' => $aData['is_active'] ?? true,
                ]);
            }
        }

        Audit::record([
            'action' => 'service.created',
            'entity_type' => 'Service',
            'entity_id' => $service->id,
            'before' => null,
            'after' => $service->toArray(),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('owner.services.index')->with('success', 'Layanan berhasil dibuat.');
    }

    public function edit(Request $request, int $id): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->with(['variants', 'addons'])
            ->firstOrFail();

        $categories = ServiceCategory::where('business_id', $business->id)
            ->orderBy('order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $usage = $this->limitEnforcer->getUsage('services', $tenant);

        return Inertia::render('Owner/Services/Form', [
            'mode' => 'edit',
            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'category_id' => $service->category_id,
                'description' => $service->description ?? '',
                'image_url' => $service->image_url,
                'price_idr' => (float) $service->price_idr,
                'duration_type' => $service->duration_type,
                'duration_minutes' => $service->duration_minutes,
                'duration_rule' => $service->duration_rule ?? [],
                'buffer_before' => $service->buffer_before,
                'buffer_after' => $service->buffer_after,
                'capacity' => $service->capacity,
                'is_active' => (bool) $service->is_active,
                'is_featured' => (bool) $service->is_featured,
                'rules' => $service->rules ?? [],
                'variants' => $service->variants->map(fn (ServiceVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price_idr' => (float) $v->price_idr,
                    'duration_minutes' => $v->duration_minutes,
                    'order' => $v->order,
                    'is_active' => (bool) $v->is_active,
                ]),
                'addons' => $service->addons->map(fn (ServiceAddon $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'price_idr' => (float) $a->price_idr,
                    'duration_minutes' => $a->duration_minutes,
                    'order' => $a->order,
                    'is_active' => (bool) $a->is_active,
                ]),
            ],
            'categories' => $categories,
            'canCreate' => true,
            'usage' => $usage,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->where('tenant_id', $tenant->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_idr' => ['required', 'numeric', 'min:0'],
            'duration_type' => ['required', 'string', Rule::in(['FIXED', 'PER_QUANTITY', 'PER_UNIT_SIZE', 'VARIABLE'])],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'duration_rule' => ['nullable', 'array'],
            'buffer_before' => ['nullable', 'integer', 'min:0', 'max:240'],
            'buffer_after' => ['nullable', 'integer', 'min:0', 'max:240'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'rules' => ['nullable', 'array'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.price_idr' => ['required', 'numeric', 'min:0'],
            'variants.*.duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'variants.*.order' => ['nullable', 'integer'],
            'variants.*.is_active' => ['boolean'],
            'addons' => ['nullable', 'array'],
            'addons.*.id' => ['nullable', 'integer'],
            'addons.*.name' => ['required', 'string', 'max:255'],
            'addons.*.price_idr' => ['required', 'numeric', 'min:0'],
            'addons.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'addons.*.order' => ['nullable', 'integer'],
            'addons.*.is_active' => ['boolean'],
        ]);

        $beforeState = $service->toArray();

        // Image handling
        if ($request->boolean('remove_image')) {
            if ($service->image_path && Storage::disk('public')->exists($service->image_path)) {
                Storage::disk('public')->delete($service->image_path);
            }
            $service->image_path = null;
        } elseif ($request->hasFile('image')) {
            if ($service->image_path && Storage::disk('public')->exists($service->image_path)) {
                Storage::disk('public')->delete($service->image_path);
            }
            $service->image_path = app(ImageOptimizationService::class)->optimizeAndStore(
                $request->file('image'),
                'services',
                'public',
                1600
            );
        }

        $service->update([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image_path' => $service->image_path,
            'price_idr' => $validated['price_idr'],
            'duration_type' => $validated['duration_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'duration_rule' => $validated['duration_rule'] ?? null,
            'buffer_before' => $validated['buffer_before'] ?? 0,
            'buffer_after' => $validated['buffer_after'] ?? 0,
            'capacity' => $validated['capacity'],
            'is_active' => $validated['is_active'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
            'rules' => $validated['rules'] ?? null,
        ]);

        // Sync variants
        $keptVariantIds = [];
        if (! empty($validated['variants'])) {
            foreach ($validated['variants'] as $vIndex => $vData) {
                if (! empty($vData['id'])) {
                    $variant = ServiceVariant::where('service_id', $service->id)->where('id', $vData['id'])->first();
                    if ($variant) {
                        $variant->update([
                            'name' => $vData['name'],
                            'price_idr' => $vData['price_idr'],
                            'duration_minutes' => $vData['duration_minutes'],
                            'order' => $vData['order'] ?? $vIndex,
                            'is_active' => $vData['is_active'] ?? true,
                        ]);
                        $keptVariantIds[] = $variant->id;
                    }
                } else {
                    $newVariant = ServiceVariant::create([
                        'tenant_id' => $tenant->id,
                        'service_id' => $service->id,
                        'name' => $vData['name'],
                        'price_idr' => $vData['price_idr'],
                        'duration_minutes' => $vData['duration_minutes'],
                        'order' => $vData['order'] ?? $vIndex,
                        'is_active' => $vData['is_active'] ?? true,
                    ]);
                    $keptVariantIds[] = $newVariant->id;
                }
            }
        }
        ServiceVariant::where('service_id', $service->id)->whereNotIn('id', $keptVariantIds)->delete();

        // Sync addons
        $keptAddonIds = [];
        if (! empty($validated['addons'])) {
            foreach ($validated['addons'] as $aIndex => $aData) {
                if (! empty($aData['id'])) {
                    $addon = ServiceAddon::where('service_id', $service->id)->where('id', $aData['id'])->first();
                    if ($addon) {
                        $addon->update([
                            'name' => $aData['name'],
                            'price_idr' => $aData['price_idr'],
                            'duration_minutes' => $aData['duration_minutes'] ?? 0,
                            'order' => $aData['order'] ?? $aIndex,
                            'is_active' => $aData['is_active'] ?? true,
                        ]);
                        $keptAddonIds[] = $addon->id;
                    }
                } else {
                    $newAddon = ServiceAddon::create([
                        'tenant_id' => $tenant->id,
                        'service_id' => $service->id,
                        'name' => $aData['name'],
                        'price_idr' => $aData['price_idr'],
                        'duration_minutes' => $aData['duration_minutes'] ?? 0,
                        'order' => $aData['order'] ?? $aIndex,
                        'is_active' => $aData['is_active'] ?? true,
                    ]);
                    $keptAddonIds[] = $newAddon->id;
                }
            }
        }
        ServiceAddon::where('service_id', $service->id)->whereNotIn('id', $keptAddonIds)->delete();

        Audit::record([
            'action' => 'service.updated',
            'entity_type' => 'Service',
            'entity_id' => $service->id,
            'before' => $beforeState,
            'after' => $service->toArray(),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('owner.services.index')->with('success', 'Layanan berhasil diperbarui.');
    }

    public function archive(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        $beforeState = ['archived_at' => $service->archived_at];
        $isArchiving = is_null($service->archived_at);

        $service->update([
            'archived_at' => $isArchiving ? now() : null,
        ]);

        Audit::record([
            'action' => $isArchiving ? 'service.archived' : 'service.unarchived',
            'entity_type' => 'Service',
            'entity_id' => $service->id,
            'before' => $beforeState,
            'after' => ['archived_at' => $service->archived_at],
            'tenant_id' => $tenant->id,
        ]);

        $msg = $isArchiving
            ? 'Layanan berhasil diarsipkan. Layanan tidak akan muncul untuk booking baru.'
            : 'Layanan berhasil diaktifkan kembali dari arsip.';

        return redirect()->back()->with('success', $msg);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->firstOrFail();

        // Check if service has bookings in database (PRD 122, 144)
        // If bookings table has records referencing this service, archive it instead of deleting.
        $hasBookings = false;
        if (Schema::hasTable('bookings')) {
            $hasBookings = DB::table('bookings')
                ->where('tenant_id', $tenant->id)
                ->whereJsonContains('service_snapshot->id', $service->id)
                ->exists();
        }

        if ($hasBookings) {
            $service->update(['archived_at' => now(), 'is_active' => false]);

            Audit::record([
                'action' => 'service.archived',
                'entity_type' => 'Service',
                'entity_id' => $service->id,
                'before' => null,
                'after' => ['archived_at' => now(), 'reason' => 'has_historical_bookings'],
                'tenant_id' => $tenant->id,
            ]);

            return redirect()->back()->with('success', 'Layanan memiliki riwayat pemesanan sehingga diarsipkan otomatis demi integritas data.');
        }

        $beforeState = $service->toArray();

        if ($service->image_path && Storage::disk('public')->exists($service->image_path)) {
            Storage::disk('public')->delete($service->image_path);
        }

        $service->delete();

        Audit::record([
            'action' => 'service.deleted',
            'entity_type' => 'Service',
            'entity_id' => $service->id,
            'before' => $beforeState,
            'after' => null,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('owner.services.index')->with('success', 'Layanan berhasil dihapus.');
    }
}
