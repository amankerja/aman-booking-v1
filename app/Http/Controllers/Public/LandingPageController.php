<?php

namespace App\Http\Controllers\Public;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LandingPageController extends Controller
{
    public function __construct(
        protected BusinessCalendarService $calendarService,
        protected AvailabilityService $availabilityService
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
    public function booking(Request $request, string $slug): InertiaResponse|Response
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
            ->with(['category', 'variants', 'addons', 'resourceRules.resource'])
            ->orderBy('id', 'asc')
            ->get();

        // Selectable public staff resources
        $staff = Resource::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where(function ($q) use ($business) {
                $q->where('business_id', $business->id)->orWhereNull('business_id');
            })
            ->where('visibility', 'PUBLIC')
            ->whereNull('archived_at')
            ->where(function ($q) {
                $q->whereHas('resourceType', function ($sub) {
                    $sub->where('is_staff', true);
                })->orWhereNull('resource_type_id');
            })
            ->get(['id', 'name', 'code', 'visibility']);

        Inertia::setRootView('public');

        $preselectedId = $request->query('service_id') ? (int) $request->query('service_id') : null;

        return Inertia::render('Public/Booking', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'description' => $business->description,
                'address' => $business->address,
                'whatsapp' => $business->whatsapp,
                'phone' => $business->phone,
                'logo_url' => $business->logo_url,
                'booking_rules' => $business->booking_rules,
            ],
            'services' => $services,
            'staff' => $staff,
            'preselectedServiceId' => $preselectedId,
            'quickMode' => $request->boolean('quick'),
        ]);
    }

    /**
     * Get available booking slots for a service on a given date (PRD 19, 20, 30, 218).
     */
    public function availability(Request $request, string $slug): JsonResponse
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
        if (! $tenant || in_array($tenant->status, ['SUSPENDED', 'EXPIRED'], true)) {
            return response()->json([
                'message' => 'Layanan sementara tidak dapat menerima pemesanan.',
                'error_code' => 'TENANT_UNAVAILABLE',
            ], 503);
        }

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            'staff_id' => 'nullable|integer',
            'variant_id' => 'nullable|integer',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'integer',
        ]);

        /** @var Service|null $service */
        $service = Service::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('id', $validated['service_id'])
            ->first();

        if (! $service) {
            return response()->json([
                'message' => 'Layanan tidak ditemukan atau sudah tidak aktif.',
                'error_code' => 'SERVICE_NOT_FOUND',
            ], 404);
        }

        $tz = $business->timezone ?: 'Asia/Jakarta';

        /** @var array<string, mixed> $options */
        $options = [
            'staff_id' => $validated['staff_id'] ?? null,
            'preferred_staff_id' => $validated['staff_id'] ?? null,
            'variant_id' => $validated['variant_id'] ?? null,
            'addon_ids' => $validated['addon_ids'] ?? [],
            'include_unavailable' => true,
        ];

        $rawSlots = $this->availabilityService->getSlotsForDate(
            $tenant,
            $service,
            $validated['date'],
            $options
        );

        $slots = $rawSlots->map(function ($slot) use ($tz) {
            $start = Carbon::parse($slot['start_at'])->setTimezone($tz);
            $end = Carbon::parse($slot['end_at'])->setTimezone($tz);

            return [
                'start_time' => $start->format('H:i'),
                'end_time' => $end->format('H:i'),
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'available' => (bool) $slot['is_available'],
                'reason' => $slot['reason_code'],
                'message' => $slot['reason_message'],
                'available_capacity' => $slot['available_capacity'],
                'available_staff' => $slot['available_staff'],
            ];
        })->values();

        return response()->json([
            'date' => $validated['date'],
            'timezone' => $tz,
            'slots' => $slots,
        ]);
    }

    /**
     * Submit booking reservation with idempotency protection (PRD 30, 195, 204.3, 215.1).
     */
    public function storeBooking(Request $request, string $slug, CreateBooking $createBooking): JsonResponse|RedirectResponse
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
        if (! $tenant || in_array($tenant->status, ['SUSPENDED', 'EXPIRED'], true)) {
            return response()->json([
                'message' => 'Layanan sementara tidak dapat menerima pemesanan.',
                'error_code' => 'TENANT_UNAVAILABLE',
            ], 503);
        }

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'integer',
            'staff_id' => 'nullable|integer',
            'start_at' => 'required|date',
            'customer_name' => 'required|string|max:100',
            'customer_phone' => 'required|string|max:25',
            'customer_email' => 'nullable|email|max:100',
            'customer_notes' => 'nullable|string|max:500',
            'custom_fields' => 'nullable|array',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        /** @var Service|null $service */
        $service = Service::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('id', $validated['service_id'])
            ->first();

        if (! $service) {
            return response()->json([
                'message' => 'Layanan yang dipilih tidak valid atau sudah tidak aktif.',
                'error_code' => 'SERVICE_NOT_FOUND',
            ], 404);
        }

        /** @var string $idempotencyKey */
        $idempotencyKey = $request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? (string) Str::uuid());

        try {
            $booking = $createBooking->execute([
                'tenant' => $tenant,
                'service' => $service,
                'customer' => [
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                    'email' => $validated['customer_email'] ?? null,
                ],
                'start_at' => $validated['start_at'],
                'variant_id' => $validated['variant_id'] ?? null,
                'addon_ids' => $validated['addon_ids'] ?? [],
                'staff_id' => $validated['staff_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'source' => 'PUBLIC_WEB',
            ]);
        } catch (BookingException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        $successUrl = route('public.booking.success', [
            'slug' => $business->slug,
            'code' => $booking->code,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'code' => $booking->code,
                'redirect_url' => $successUrl,
            ]);
        }

        return redirect()->to($successUrl);
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

        Inertia::setRootView('public');

        return Inertia::render('Public/BookingSuccess', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'whatsapp' => $business->whatsapp,
            ],
            'booking' => [
                'code' => $booking->code,
                'status' => $booking->status_category->value,
                'status_category' => $booking->status_category->value,
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
                'total_idr' => $booking->total_idr,
                'manage_token' => $booking->manage_token,
                'service' => [
                    'name' => $booking->service->name,
                    'duration_minutes' => $booking->service->duration_minutes,
                    'price_idr' => $booking->service->price_idr,
                ],
                'customer' => [
                    'name' => $booking->customer->name,
                    'phone' => $booking->customer->phone_e164,
                ],
            ],
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

        Inertia::setRootView('public');

        return Inertia::render('Public/BookingManage', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'whatsapp' => $business->whatsapp,
            ],
            'booking' => [
                'code' => $booking->code,
                'status' => $booking->status_category->value,
                'status_category' => $booking->status_category->value,
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
                'total_idr' => $booking->total_idr,
                'manage_token' => $booking->manage_token,
                'service' => [
                    'name' => $booking->service->name,
                    'duration_minutes' => $booking->service->duration_minutes,
                    'price_idr' => $booking->service->price_idr,
                ],
                'customer' => [
                    'name' => $booking->customer->name,
                    'phone' => $booking->customer->phone_e164,
                ],
            ],
        ]);
    }
}
