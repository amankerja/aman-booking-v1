<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Business\Services\LandingPageBuilderService;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LandingPageBuilderController extends Controller
{
    public function __construct(
        protected LandingPageBuilderService $builderService,
        protected BusinessCalendarService $calendarService
    ) {}

    /**
     * Display the Landing Page Builder editor (PRD 28, 68).
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $config = $this->builderService->getLandingConfig($business);

        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['category:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price_idr', 'category_id', 'capacity', 'description']);

        $publicUrl = url("/{$business->slug}");
        $isPublished = $business->published_at !== null;

        if ($request->wantsJson()) {
            return response()->json([
                'business' => $business,
                'config' => $config,
                'is_published' => $isPublished,
                'public_url' => $publicUrl,
            ]);
        }

        return Inertia::render('Owner/LandingBuilder/Index', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => $business->logo_url,
                'whatsapp' => $business->whatsapp,
                'phone' => $business->phone,
                'address' => $business->address,
                'city' => $business->city,
                'timezone' => $business->timezone,
                'published_at' => $business->published_at?->toIso8601String(),
                'is_published' => $isPublished,
            ],
            'config' => $config,
            'servicesCount' => $services->count(),
            'publicUrl' => $publicUrl,
        ]);
    }

    /**
     * Update landing page configuration (PRD 68, 69).
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'theme' => ['nullable', 'array'],
            'theme.primary_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'theme.font_preset' => ['nullable', 'string', 'in:inter,plus_jakarta'],
            'theme.banner_image_url' => ['nullable', 'string', 'max:2000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['required', 'string', 'in:hero,services,features,about,faq,contact'],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.data' => ['nullable', 'array'],
        ]);

        $updatedBusiness = $this->builderService->saveLandingConfig($business, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tata letak dan konten landing page berhasil disimpan.',
                'config' => $this->builderService->getLandingConfig($updatedBusiness),
            ]);
        }

        return redirect()->back()->with('success', 'Pengaturan landing page berhasil disimpan.');
    }

    /**
     * Toggle publish status of the landing page (PRD 29, 205).
     */
    public function publish(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $isPublished = $request->has('publish')
            ? (bool) $request->input('publish')
            : ($business->published_at === null);

        $this->builderService->setPublished($business, $isPublished);

        $msg = $isPublished
            ? 'Landing page berhasil dipublikasikan dan sekarang dapat diakses secara publik.'
            : 'Landing page berhasil di-unpublish. Halaman publik sementara ditutup.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'is_published' => $isPublished,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Reset landing page configuration to system default template.
     */
    public function resetDefaults(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $this->builderService->resetLandingConfig($business);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi landing page berhasil dikembalikan ke standar bawaan.',
                'config' => $this->builderService->getLandingConfig($business),
            ]);
        }

        return redirect()->back()->with('success', 'Konfigurasi landing page berhasil direset ke bawaan.');
    }

    /**
     * Render landing page preview in iframe (PRD 68).
     * Works even if business is not yet published.
     */
    public function preview(Request $request): View|Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::with(['hours', 'calendarExceptions'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $config = $this->builderService->getLandingConfig($business);

        // Fetch active services
        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['category'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Calculate hours
        $tz = $business->timezone ?: 'Asia/Jakarta';
        $nowUtc = now('UTC');
        $isOpenNow = $this->calendarService->isBusinessOpenAt($business, $nowUtc);

        $nowLocal = Carbon::now($tz);
        $todayDayOfWeek = (int) $nowLocal->dayOfWeek; // 0=Sunday, 1=Monday...

        $dayNames = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        $hoursMap = $business->hours->keyBy('day_of_week');
        $scheduleList = [];
        $todayHoursText = 'Tutup Hari Ini';

        foreach ($dayNames as $dayIndex => $dayName) {
            $h = $hoursMap->get($dayIndex);
            $isToday = ($dayIndex === $todayDayOfWeek);
            $text = 'Tutup';

            if ($h && $h->is_open && $h->open_time && $h->close_time) {
                $openFmt = substr((string) $h->open_time, 0, 5);
                $closeFmt = substr((string) $h->close_time, 0, 5);
                $text = "{$openFmt} - {$closeFmt}";
            }

            if ($isToday && $h && $h->is_open) {
                $todayHoursText = "Buka: {$text}";
            }

            $scheduleList[] = [
                'day_index' => $dayIndex,
                'day_name' => $dayName,
                'is_today' => $isToday,
                'is_open' => $h ? $h->is_open : false,
                'hours_text' => $text,
            ];
        }

        // WhatsApp greeting
        $rawWa = (string) ($business->whatsapp ?: $business->phone);
        $cleanPhone = preg_replace('/\D+/', '', $rawWa);
        if ($cleanPhone !== null && $cleanPhone !== '') {
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62'.substr($cleanPhone, 1);
            } elseif (str_starts_with($cleanPhone, '8')) {
                $cleanPhone = '62'.$cleanPhone;
            }
        }
        $waGreeting = rawurlencode("Halo {$business->name}, saya ingin bertanya mengenai layanan.");
        $whatsappUrl = ($cleanPhone !== null && $cleanPhone !== '') ? "https://wa.me/{$cleanPhone}?text={$waGreeting}" : null;

        $bookingUrl = url("/{$business->slug}/booking");

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'description' => $business->description ?: '',
            'url' => url("/{$business->slug}"),
        ];

        return view('public.landing', [
            'business' => $business,
            'services' => $services,
            'isOpenNow' => $isOpenNow,
            'todayHoursText' => $todayHoursText,
            'scheduleList' => $scheduleList,
            'whatsappUrl' => $whatsappUrl,
            'bookingUrl' => $bookingUrl,
            'landingConfig' => $config,
            'sections' => $config['sections'],
            'theme' => $config['theme'],
            'timezone' => $tz,
            'jsonLd' => $jsonLd,
            'isPreview' => true,
        ]);
    }
}
