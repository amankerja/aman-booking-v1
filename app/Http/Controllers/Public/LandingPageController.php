<?php

namespace App\Http\Controllers\Public;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Services\FormService;
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

        // Fetch active forms for the tenant with sorted fields (PRD 26, 27)
        $forms = BookingForm::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where('is_active', true)
            ->with(['fields' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order', 'asc');
            }])
            ->get();

        if ($forms->isEmpty()) {
            /** @var FormService $formService */
            $formService = app(FormService::class);
            $defaultForm = $formService->getFormForService((int) $business->tenant_id);
            if ($defaultForm) {
                $forms = collect([$defaultForm]);
            }
        }

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
            'forms' => $forms,
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

        /** @var FormService $formService */
        $formService = app(FormService::class);
        $form = $formService->getFormForService((int) $tenant->id, (int) $validated['service_id']);

        $customFieldsToSave = [];
        if ($form) {
            $submittedCustomFields = (array) $request->input('custom_fields', []);
            $uploadedFiles = (array) $request->file('custom_fields', []);

            try {
                $customFieldsToSave = $formService->validateAndSanitizeSubmission(
                    $form,
                    $submittedCustomFields,
                    $uploadedFiles
                );
            } catch (BookingException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                ], 422);
            }
        }

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
                'custom_fields' => $customFieldsToSave,
                'idempotency_key' => $idempotencyKey,
                'source' => 'PUBLIC_WEB',
            ]);
        } catch (BookingException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        $rawToken = $booking->raw_manage_token ?? null;
        if ($rawToken) {
            session()->flash('raw_manage_token_'.$booking->code, $rawToken);
        }

        $successUrl = route('public.booking.success', [
            'slug' => $business->slug,
            'code' => $booking->code,
        ]);
        if ($rawToken) {
            $successUrl .= '?token='.$rawToken;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'code' => $booking->code,
                'redirect_url' => $successUrl,
                'manage_token' => $rawToken,
            ]);
        }

        return redirect()->to($successUrl);
    }

    /**
     * Display booking success confirmation page (Phase 2.3).
     */
    public function success(Request $request, string $slug, string $code): View|InertiaResponse|Response
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
            ->with(['customer', 'service', 'allocations.resource.resourceType'])
            ->first();

        if (! $booking) {
            abort(404, 'Kode booking tidak ditemukan.');
        }

        Inertia::setRootView('public');

        $rawToken = $request->query('token') ?: session('raw_manage_token_'.$booking->code);

        $allocatedStaff = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role === 'staff' || ($alloc->resource && $alloc->resource->resourceType && $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        $allocatedResource = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role !== 'staff' && (! $alloc->resource || ! $alloc->resource->resourceType || ! $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        return Inertia::render('Public/BookingSuccess', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'whatsapp' => $business->whatsapp,
                'phone' => $business->phone,
                'address' => $business->address,
            ],
            'booking' => [
                'code' => $booking->code,
                'status' => $booking->status_category->value,
                'status_category' => $booking->status_category->value,
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
                'total_idr' => $booking->total_idr,
                'manage_token' => $rawToken,
                'staff_name' => $allocatedStaff,
                'resource_name' => $allocatedResource,
                'service' => [
                    'id' => $booking->service->id,
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
    public function manage(Request $request, string $slug, string $token): View|InertiaResponse|Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        $hashedToken = hash('sha256', $token);

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where(function ($q) use ($token, $hashedToken) {
                $q->where('manage_token', $hashedToken)
                    ->orWhere('manage_token', $token);
            })
            ->with(['customer', 'service', 'allocations.resource.resourceType'])
            ->first();

        if (! $booking) {
            abort(404, 'Token kelola booking tidak valid.');
        }

        if ($booking->manage_token_expires_at && $booking->manage_token_expires_at->isPast()) {
            abort(410, 'Tautan kelola booking telah kedaluwarsa. Silakan hubungi admin bisnis.');
        }

        Inertia::setRootView('public');

        /** @var array<string, mixed> $bookingRules */
        $bookingRules = is_array($business->booking_rules) ? $business->booking_rules : [];
        $rescheduleDeadlineHours = isset($bookingRules['reschedule_deadline_hours']) ? (int) $bookingRules['reschedule_deadline_hours'] : 2;
        $cancelDeadlineHours = isset($bookingRules['cancellation_deadline_hours']) ? (int) $bookingRules['cancellation_deadline_hours'] : 2;
        $maxReschedules = isset($bookingRules['max_reschedule_times']) ? (int) $bookingRules['max_reschedule_times'] : 2;

        $hoursUntilBooking = now()->diffInHours($booking->start_at, false);
        $isFutureBooking = $booking->start_at->isFuture();

        $isActiveStatus = in_array($booking->status_category->value, ['CONFIRMED', 'PENDING'], true);

        $canReschedule = $isActiveStatus
            && $isFutureBooking
            && $booking->reschedule_count < $maxReschedules
            && $hoursUntilBooking >= $rescheduleDeadlineHours;

        $canCancel = $isActiveStatus
            && $isFutureBooking
            && $hoursUntilBooking >= $cancelDeadlineHours;

        $rescheduleDisabledReason = null;
        if (! $isActiveStatus) {
            $rescheduleDisabledReason = 'Status reservasi saat ini tidak dapat diubah.';
        } elseif (! $isFutureBooking) {
            $rescheduleDisabledReason = 'Waktu reservasi sudah terlewat.';
        } elseif ($booking->reschedule_count >= $maxReschedules) {
            $rescheduleDisabledReason = "Batas perubahan jadwal ({$maxReschedules}x) telah tercapai.";
        } elseif ($hoursUntilBooking < $rescheduleDeadlineHours) {
            $rescheduleDisabledReason = "Perubahan jadwal hanya dapat dilakukan maksimal {$rescheduleDeadlineHours} jam sebelum jadwal.";
        }

        $cancelDisabledReason = null;
        if (! $isActiveStatus) {
            $cancelDisabledReason = 'Status reservasi saat ini tidak dapat dibatalkan.';
        } elseif (! $isFutureBooking) {
            $cancelDisabledReason = 'Waktu reservasi sudah terlewat.';
        } elseif ($hoursUntilBooking < $cancelDeadlineHours) {
            $cancelDisabledReason = "Pembatalan hanya dapat dilakukan maksimal {$cancelDeadlineHours} jam sebelum jadwal.";
        }

        $allocatedStaff = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role === 'staff' || ($alloc->resource && $alloc->resource->resourceType && $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        $allocatedResource = $booking->allocations
            ->filter(fn ($alloc) => $alloc->role !== 'staff' && (! $alloc->resource || ! $alloc->resource->resourceType || ! $alloc->resource->resourceType->is_staff))
            ->map(fn ($alloc) => $alloc->resource?->name)
            ->filter()
            ->first();

        return Inertia::render('Public/BookingManage', [
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone ?: 'Asia/Jakarta',
                'whatsapp' => $business->whatsapp,
                'phone' => $business->phone,
                'address' => $business->address,
                'booking_rules' => [
                    'reschedule_deadline_hours' => $rescheduleDeadlineHours,
                    'cancellation_deadline_hours' => $cancelDeadlineHours,
                    'max_reschedule_times' => $maxReschedules,
                ],
            ],
            'booking' => [
                'code' => $booking->code,
                'status' => $booking->status_category->value,
                'status_category' => $booking->status_category->value,
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
                'total_idr' => $booking->total_idr,
                'reschedule_count' => $booking->reschedule_count,
                'manage_token' => $token,
                'staff_name' => $allocatedStaff,
                'resource_name' => $allocatedResource,
                'service' => [
                    'id' => $booking->service->id,
                    'name' => $booking->service->name,
                    'duration_minutes' => $booking->service->duration_minutes,
                    'price_idr' => $booking->service->price_idr,
                ],
                'customer' => [
                    'name' => $booking->customer->name,
                    'phone' => $booking->customer->phone_e164,
                ],
            ],
            'policy' => [
                'can_reschedule' => $canReschedule,
                'reschedule_disabled_reason' => $rescheduleDisabledReason,
                'can_cancel' => $canCancel,
                'cancel_disabled_reason' => $cancelDisabledReason,
                'reschedule_deadline_hours' => $rescheduleDeadlineHours,
                'cancel_deadline_hours' => $cancelDeadlineHours,
                'max_reschedules' => $maxReschedules,
                'reschedules_remaining' => max(0, $maxReschedules - $booking->reschedule_count),
            ],
        ]);
    }

    /**
     * Reschedule booking to a new time window (PRD 33, 213.1, 215.1).
     */
    public function reschedule(
        Request $request,
        string $slug,
        string $token,
        BookingService $bookingService
    ): JsonResponse {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            return response()->json([
                'code' => 'TENANT_UNAVAILABLE',
                'message' => 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        $hashedToken = hash('sha256', $token);

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where(function ($q) use ($token, $hashedToken) {
                $q->where('manage_token', $hashedToken)
                    ->orWhere('manage_token', $token);
            })
            ->with(['customer', 'service', 'allocations'])
            ->first();

        if (! $booking) {
            return response()->json([
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Tautan kelola booking tidak valid atau telah kedaluwarsa.',
            ], 404);
        }

        if ($booking->manage_token_expires_at && $booking->manage_token_expires_at->isPast()) {
            return response()->json([
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Tautan kelola booking telah kedaluwarsa. Silakan hubungi admin bisnis.',
            ], 410);
        }

        $validated = $request->validate([
            'new_start_at' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var array<string, mixed> $bookingRules */
        $bookingRules = is_array($business->booking_rules) ? $business->booking_rules : [];
        $rescheduleDeadlineHours = isset($bookingRules['reschedule_deadline_hours']) ? (int) $bookingRules['reschedule_deadline_hours'] : 2;
        $maxReschedules = isset($bookingRules['max_reschedule_times']) ? (int) $bookingRules['max_reschedule_times'] : 2;

        // Check current status
        if (! in_array($booking->status_category->value, ['CONFIRMED', 'PENDING'], true)) {
            return response()->json([
                'code' => 'INVALID_TRANSITION',
                'message' => 'Status booking saat ini tidak dapat diubah.',
            ], 422);
        }

        // Check reschedule limit
        if ($booking->reschedule_count >= $maxReschedules) {
            return response()->json([
                'code' => 'RESCHEDULE_LIMIT_REACHED',
                'message' => 'Batas perubahan jadwal untuk booking ini sudah tercapai.',
            ], 422);
        }

        // Check deadline
        $hoursUntilBooking = now()->diffInHours($booking->start_at, false);
        if ($hoursUntilBooking < $rescheduleDeadlineHours) {
            return response()->json([
                'code' => 'RESCHEDULE_DEADLINE_PASSED',
                'message' => 'Batas waktu perubahan jadwal sudah lewat. Silakan hubungi bisnis.',
            ], 422);
        }

        // Parse new start at
        $tz = $business->timezone ?: 'Asia/Jakarta';
        try {
            $newStartUtc = Carbon::parse($validated['new_start_at'], $tz)->setTimezone('UTC');
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'VALIDATION_FAILED',
                'message' => 'Format waktu tidak valid.',
            ], 422);
        }

        if ($newStartUtc->isPast()) {
            return response()->json([
                'code' => 'VALIDATION_FAILED',
                'message' => 'Waktu reservasi baru harus di masa mendatang.',
            ], 422);
        }

        if (! $this->calendarService->isBusinessOpenAt($business, $newStartUtc)) {
            return response()->json([
                'code' => 'OUTSIDE_BUSINESS_HOURS',
                'message' => 'Waktu yang dipilih di luar jam operasional bisnis.',
            ], 422);
        }

        try {
            $updatedBooking = $bookingService->reschedule(
                $booking,
                $newStartUtc,
                [
                    'source' => 'CUSTOMER_PORTAL',
                    'reason' => $validated['reason'] ?? 'Customer reschedule via portal',
                ]
            );
        } catch (BookingException $e) {
            return response()->json([
                'code' => $e->getErrorCode(),
                'message' => $e->getMessageUser(),
                'message_dev' => $e->getMessageDev(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Jadwal reservasi Anda berhasil diubah.',
            'booking' => [
                'code' => $updatedBooking->code,
                'start_at' => $updatedBooking->start_at->toIso8601String(),
                'end_at' => $updatedBooking->end_at->toIso8601String(),
                'reschedule_count' => $updatedBooking->reschedule_count,
            ],
        ]);
    }

    /**
     * Cancel booking via customer portal (PRD 34, 213.1, 215.1).
     */
    public function cancel(
        Request $request,
        string $slug,
        string $token,
        BookingService $bookingService
    ): JsonResponse {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            return response()->json([
                'code' => 'TENANT_UNAVAILABLE',
                'message' => 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.',
            ], 404);
        }

        $hashedToken = hash('sha256', $token);

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where(function ($q) use ($token, $hashedToken) {
                $q->where('manage_token', $hashedToken)
                    ->orWhere('manage_token', $token);
            })
            ->with(['customer', 'service'])
            ->first();

        if (! $booking) {
            return response()->json([
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Tautan kelola booking tidak valid atau telah kedaluwarsa.',
            ], 404);
        }

        if ($booking->manage_token_expires_at && $booking->manage_token_expires_at->isPast()) {
            return response()->json([
                'code' => 'INVALID_OR_EXPIRED_TOKEN',
                'message' => 'Tautan kelola booking telah kedaluwarsa. Silakan hubungi admin bisnis.',
            ], 410);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var array<string, mixed> $bookingRules */
        $bookingRules = is_array($business->booking_rules) ? $business->booking_rules : [];
        $cancelDeadlineHours = isset($bookingRules['cancellation_deadline_hours']) ? (int) $bookingRules['cancellation_deadline_hours'] : 2;

        // Check status
        if (! in_array($booking->status_category->value, ['CONFIRMED', 'PENDING'], true)) {
            return response()->json([
                'code' => 'INVALID_TRANSITION',
                'message' => 'Status reservasi saat ini tidak dapat dibatalkan.',
            ], 422);
        }

        // Check deadline
        $hoursUntilBooking = now()->diffInHours($booking->start_at, false);
        if ($hoursUntilBooking < $cancelDeadlineHours) {
            return response()->json([
                'code' => 'CANCEL_DEADLINE_PASSED',
                'message' => 'Batas waktu pembatalan sudah lewat. Silakan hubungi bisnis.',
            ], 422);
        }

        try {
            $updatedBooking = $bookingService->cancel(
                $booking,
                $validated['reason'] ?? 'Customer requested cancellation via portal',
                [
                    'source' => 'CUSTOMER_PORTAL',
                ]
            );
        } catch (BookingException $e) {
            return response()->json([
                'code' => $e->getErrorCode(),
                'message' => $e->getMessageUser(),
                'message_dev' => $e->getMessageDev(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => 'Reservasi Anda telah berhasil dibatalkan.',
            'booking' => [
                'code' => $updatedBooking->code,
                'status' => $updatedBooking->status_category->value,
            ],
        ]);
    }

    /**
     * Download RFC 5545 iCalendar (.ics) file for booking (PRD 32, 215.1).
     */
    public function calendar(Request $request, string $slug, string $identifier): Response
    {
        /** @var Business|null $business */
        $business = Business::withoutGlobalScopes()
            ->with(['tenant'])
            ->where('slug', $slug)
            ->first();

        if (! $business || $business->published_at === null) {
            abort(404, 'Halaman bisnis tidak ditemukan atau belum dipublikasikan.');
        }

        $hashed = hash('sha256', $identifier);

        /** @var Booking|null $booking */
        $booking = Booking::withoutGlobalScopes()
            ->where('tenant_id', $business->tenant_id)
            ->where(function ($q) use ($identifier, $hashed) {
                $q->where('code', $identifier)
                    ->orWhere('manage_token', $hashed)
                    ->orWhere('manage_token', $identifier);
            })
            ->with(['service', 'customer'])
            ->first();

        if (! $booking) {
            abort(404, 'Reservasi tidak ditemukan.');
        }

        $dtStart = $booking->start_at->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtEnd = $booking->end_at->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $dtStamp = now()->setTimezone('UTC')->format('Ymd\THis\Z');
        $summary = "{$booking->service->name} - {$business->name}";
        $description = "Reservasi: {$booking->code}\\nLayanan: {$booking->service->name}\\nBisnis: {$business->name}\\nCustomer: {$booking->customer->name}\\nTelepon: {$business->whatsapp}";
        $location = $business->address ?: $business->city ?: 'Indonesia';

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//AMAN BOOKING//ID\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$booking->code}@amanbooking.com\r\n";
        $ics .= "DTSTAMP:{$dtStamp}\r\n";
        $ics .= "DTSTART:{$dtStart}\r\n";
        $ics .= "DTEND:{$dtEnd}\r\n";
        $ics .= "SUMMARY:{$summary}\r\n";
        $ics .= "DESCRIPTION:{$description}\r\n";
        $ics .= "LOCATION:{$location}\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"reservasi-{$booking->code}.ics\"",
        ]);
    }
}
