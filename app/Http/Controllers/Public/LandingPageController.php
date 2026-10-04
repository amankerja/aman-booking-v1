<?php

namespace App\Http\Controllers\Public;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Service\Models\Service;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LandingPageController extends Controller
{
    public function __construct(
        protected BusinessCalendarService $calendarService
    ) {}

    /**
     * Display public landing page (Server-Rendered Blade for maximum SEO & performance).
     * PRD 28, 29, 167, 184, 205, 215.1.
     */
    public function show(string $slug): View|Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant', 'hours', 'calendarExceptions'])
            ->where('slug', $slug)
            ->first();

        // 1. 404 if not found or not published (PRD 205)
        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        // 2. 503 if tenant is suspended or expired (PRD 205, 210 point 5)
        $tenant = $business->tenant;
        if ($tenant && in_array($tenant->status, ['SUSPENDED', 'EXPIRED'], true)) {
            return response()->view('public.tenant-unavailable', [
                'business' => $business,
            ], 503);
        }

        // 3. Fetch active services (PRD 28 Layanan)
        $services = Service::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->with(['category', 'variants', 'addons'])
            ->orderBy('id', 'asc')
            ->get();

        // 4. Calculate live open/close status in business timezone
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

        // 5. WhatsApp URL (PRD 184)
        $rawWa = (string) ($business->whatsapp ?: $business->phone);
        $cleanPhone = preg_replace('/\D+/', '', $rawWa);
        if ($cleanPhone !== null && $cleanPhone !== '') {
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62'.substr($cleanPhone, 1);
            } elseif (str_starts_with($cleanPhone, '8')) {
                $cleanPhone = '62'.$cleanPhone;
            }
        }
        $waGreeting = rawurlencode("Halo {$business->name}, saya ingin bertanya mengenai layanan dan reservasi jadwal.");
        $whatsappUrl = ($cleanPhone !== null && $cleanPhone !== '') ? "https://wa.me/{$cleanPhone}?text={$waGreeting}" : null;

        $bookingUrl = url("/{$business->slug}/booking");

        // 6. Section configuration from JSON settings
        /** @var mixed $rawSettings */
        $rawSettings = $business->settings;
        /** @var array<string, mixed> $settings */
        $settings = is_array($rawSettings) ? $rawSettings : [];
        /** @var array<string, mixed> $landingSections */
        $landingSections = (array) ($settings['landing_sections'] ?? []);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'description' => $business->description ?: '',
            'url' => url()->current(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $business->address ?: '',
                'addressLocality' => $business->city ?: 'Indonesia',
                'addressRegion' => $business->province ?: '',
                'postalCode' => $business->postal_code ?: '',
                'addressCountry' => 'ID',
            ],
        ];

        if ($business->phone) {
            $jsonLd['telephone'] = $business->phone;
        }

        if ($business->logo_url) {
            $jsonLd['image'] = $business->logo_url;
        }

        return view('public.landing', [
            'business' => $business,
            'services' => $services,
            'isOpenNow' => $isOpenNow,
            'todayHoursText' => $todayHoursText,
            'scheduleList' => $scheduleList,
            'whatsappUrl' => $whatsappUrl,
            'bookingUrl' => $bookingUrl,
            'sections' => $landingSections,
            'timezone' => $tz,
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Display customer booking flow (React island in public.tsx, Phase 2.2).
     */
    public function booking(string $slug): InertiaResponse|Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        $tenant = $business->tenant;
        if ($tenant && in_array($tenant->status, ['SUSPENDED', 'EXPIRED'], true)) {
            return response()->view('public.tenant-unavailable', [
                'business' => $business,
            ], 503);
        }

        $services = Service::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->with(['category', 'variants', 'addons'])
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Public/Booking', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'description' => $business->description,
                'address' => $business->address,
                'whatsapp' => $business->whatsapp,
                'logo_url' => $business->logo_url,
            ],
            'services' => $services,
        ]);
    }

    /**
     * Display booking success confirmation page (Phase 2.3).
     */
    public function success(string $slug, string $code): View|InertiaResponse|Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('code', $code)
            ->with(['customer', 'service', 'allocations.resource'])
            ->first();

        if (! $booking) {
            abort(404, 'Kode booking tidak ditemukan.');
        }

        return Inertia::render('Public/BookingSuccess', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone,
            ],
            'booking' => $booking,
        ]);
    }

    /**
     * Display customer booking manage page (Phase 2.3).
     */
    public function manage(string $slug, string $token): View|InertiaResponse|Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('manage_token', $token)
            ->with(['customer', 'service', 'allocations.resource'])
            ->first();

        if (! $booking) {
            abort(404, 'Token kelola booking tidak valid atau telah kedaluwarsa.');
        }

        return Inertia::render('Public/BookingManage', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone,
            ],
            'booking' => $booking,
        ]);
    }
}
