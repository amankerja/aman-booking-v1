<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\BookingStatusService;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Services\CustomerService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected AvailabilityService $availabilityService,
        protected CustomerService $customerService,
        protected BookingStatusService $statusService
    ) {}

    /**
     * Display bookings list with Table, Calendar, or Kanban view (PRD 41, 42, 67).
     */
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $view = $request->string('view', 'table')->value();
        if (! in_array($view, ['table', 'calendar', 'kanban'], true)) {
            $view = 'table';
        }

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status', 'ALL')->value();
        $resourceId = $request->input('resource_id');
        $serviceId = $request->input('service_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Booking::where('tenant_id', $tenant->id)
            ->with([
                'customer',
                'service',
                'allocations.resource',
                'status',
            ])
            ->orderBy('start_at', 'desc');

        if ($search !== '') {
            $digits = preg_replace('/\D/', '', $search) ?? '';
            $normalizedPhone = Customer::normalizePhone($search);

            $query->where(function ($q) use ($search, $digits, $normalizedPhone) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search, $digits, $normalizedPhone) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_e164', 'like', "%{$search}%");

                        if ($digits !== '') {
                            $cq->orWhere('phone_e164', 'like', "%{$digits}%");
                        }
                        if ($normalizedPhone !== '') {
                            $cq->orWhere('phone_e164', 'like', "%{$normalizedPhone}%");
                        }
                    });
            });
        }

        if ($status !== 'ALL') {
            $query->where('status_category', $status);
        }

        if ($serviceId) {
            $query->where('service_id', (int) $serviceId);
        }

        if ($resourceId) {
            $query->whereHas('allocations', function ($aq) use ($resourceId) {
                $aq->where('resource_id', (int) $resourceId);
            });
        }

        if ($dateFrom) {
            $query->where('start_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('start_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Summary counts across all active categories for this tenant
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $statusCounts = [
            'ALL' => Booking::where('tenant_id', $tenant->id)->count(),
            'TODAY' => Booking::where('tenant_id', $tenant->id)->whereBetween('start_at', [$todayStart, $todayEnd])->count(),
            'PENDING' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::PENDING)->count(),
            'CONFIRMED' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::CONFIRMED)->count(),
            'CHECKED_IN' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::CHECKED_IN)->count(),
            'IN_PROGRESS' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::IN_PROGRESS)->count(),
            'COMPLETED' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::COMPLETED)->count(),
            'CANCELLED' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::CANCELLED)->count(),
            'NO_SHOW' => Booking::where('tenant_id', $tenant->id)->where('status_category', BookingStatusCategory::NO_SHOW)->count(),
        ];

        // Format data depending on view mode
        if ($view === 'table') {
            $bookingsData = $query->paginate(20)->withQueryString();
        } else {
            // Calendar and Kanban need collection (limited to 300 to avoid memory blowout)
            $bookingsData = $query->limit(300)->get();
        }

        // Active services for filters & quick booking
        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['variants', 'addons'])
            ->orderBy('name')
            ->get();

        // Active resources for filters & quick booking
        $resources = Resource::where('tenant_id', $tenant->id)
            ->where('state', 'AVAILABLE')
            ->where('is_archived', false)
            ->with('resourceType')
            ->orderBy('name')
            ->get();

        // Recent customers for quick select
        $customers = Customer::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'phone_e164', 'email']);

        // Active custom statuses for Kanban & filtering
        $statuses = $this->statusService->getStatusesForTenant($tenant);

        return Inertia::render('Owner/Bookings/Index', [
            'bookings' => $bookingsData,
            'services' => $services,
            'resources' => $resources,
            'customers' => $customers,
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
            'filters' => [
                'view' => $view,
                'search' => $search,
                'status' => $status,
                'resource_id' => $resourceId ? (int) $resourceId : null,
                'service_id' => $serviceId ? (int) $serviceId : null,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Polling feed for real-time background sync (PRD 204.4, 30-60s interval).
     */
    public function feed(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $view = $request->string('view', 'table')->value();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $status = $request->string('status', 'ALL')->value();
        $resourceId = $request->input('resource_id');

        $query = Booking::where('tenant_id', $tenant->id)
            ->with(['customer', 'service', 'allocations.resource', 'status'])
            ->orderBy('start_at', 'desc');

        if ($status !== 'ALL') {
            $query->where('status_category', $status);
        }

        if ($resourceId) {
            $query->whereHas('allocations', function ($aq) use ($resourceId) {
                $aq->where('resource_id', (int) $resourceId);
            });
        }

        if ($dateFrom) {
            $query->where('start_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('start_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($view === 'table') {
            $bookings = $query->paginate(20);
        } else {
            $bookings = $query->limit(300)->get();
        }

        return response()->json([
            'bookings' => $bookings,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get available slots for a given service and date via AvailabilityService (PRD 157, 158).
     */
    public function slots(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'staff_id' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer'],
        ]);

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)->findOrFail($validated['service_id']);

        $options = [
            'staff_id' => isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
            'preferred_staff_id' => isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
            'variant_id' => isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            'addon_ids' => $validated['addon_ids'] ?? [],
            'include_unavailable' => false,
        ];

        $slots = $this->availabilityService->getSlotsForDate(
            $tenant,
            $service,
            $validated['date'],
            $options
        );

        return response()->json([
            'service_id' => $service->id,
            'date' => $validated['date'],
            'slots' => $slots->values(),
        ]);
    }

    /**
     * Show single booking detail with timeline and allocations (PRD 67).
     */
    public function show(int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Booking $booking */
        $booking = Booking::where('tenant_id', $tenant->id)
            ->with([
                'customer',
                'service',
                'allocations.resource.resourceType',
                'statusHistory' => function ($q) {
                    $q->orderBy('created_at', 'asc');
                },
            ])
            ->findOrFail($id);

        return response()->json([
            'booking' => $booking,
        ]);
    }

    /**
     * Create Quick Booking via CreateBooking action (PRD 157, 158).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'start_at' => ['required', 'string'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'string', 'email', 'max:255'],
            'staff_id' => ['nullable', 'integer'],
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => ['integer'],
            'variant_id' => ['nullable', 'integer'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'payment_status' => ['nullable', 'string', 'in:UNPAID,PAID,PARTIAL'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)->findOrFail($validated['service_id']);

        // Resolve customer
        if (! empty($validated['customer_id'])) {
            $customerModel = Customer::where('tenant_id', $tenant->id)->findOrFail($validated['customer_id']);
            $customerData = [
                'name' => $customerModel->name,
                'phone' => $customerModel->phone_e164,
                'email' => $customerModel->email,
            ];
        } else {
            $customerModel = $this->customerService->resolveCustomer($tenant, [
                'name' => (string) $validated['customer_name'],
                'phone' => (string) $validated['customer_phone'],
                'email' => $validated['customer_email'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $customerData = [
                'name' => $customerModel->name,
                'phone' => $customerModel->phone_e164,
                'email' => $customerModel->email,
            ];
        }

        try {
            $booking = $this->bookingService->create([
                'tenant' => $tenant,
                'service' => $service,
                'customer' => $customerData,
                'start_at' => $validated['start_at'],
                'quantity' => $validated['quantity'] ?? 1,
                'variant_id' => isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
                'addon_ids' => $validated['addon_ids'] ?? [],
                'staff_id' => isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
                'resource_ids' => $validated['resource_ids'] ?? [],
                'source' => 'MANUAL',
                'requires_payment' => false,
                'actor_id' => $request->user()?->id,
                'actor_type' => 'user',
            ]);

            if (isset($validated['payment_status'])) {
                $booking->payment_status = $validated['payment_status'];
                $booking->save();
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Booking {$booking->code} berhasil dibuat.",
                    'booking' => $booking->load(['customer', 'service', 'allocations.resource']),
                ], 201);
            }

            return redirect()->route('owner.bookings.index')
                ->with('success', "Booking {$booking->code} berhasil dibuat.");
        } catch (BookingException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['start_at' => $e->getMessage()]);
        }
    }

    /**
     * Transition status of a booking with guard validation (PRD 157, 213).
     */
    public function transitionStatus(Request $request, int $id): JsonResponse|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Booking $booking */
        $booking = Booking::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'status_id' => ['nullable'],
            'reason' => ['nullable', 'string', 'max:500'],
            'bypass_payment_guard' => ['nullable', 'boolean'],
        ]);

        $statusInput = $validated['status'] ?? null;
        $statusIdInput = $validated['status_id'] ?? null;

        /** @var BookingStatus|null $customStatus */
        $customStatus = null;
        $targetCategory = null;

        // 1. If status_id is directly given
        if (! empty($statusIdInput)) {
            $customStatus = BookingStatus::where('tenant_id', $tenant->id)->find($statusIdInput);
            if ($customStatus) {
                $targetCategory = $customStatus->category;
            }
        }

        // 2. If status was provided as numeric ID, slug, or category name
        if ($targetCategory === null && ! empty($statusInput)) {
            if (is_numeric($statusInput)) {
                $customStatus = BookingStatus::where('tenant_id', $tenant->id)->find((int) $statusInput);
                if ($customStatus) {
                    $targetCategory = $customStatus->category;
                }
            } else {
                // Try finding by slug or name
                $customStatus = BookingStatus::where('tenant_id', $tenant->id)
                    ->where(function ($q) use ($statusInput) {
                        $q->where('slug', $statusInput)
                            ->orWhere('name', $statusInput);
                    })->first();

                if ($customStatus) {
                    $targetCategory = $customStatus->category;
                } else {
                    // Try parsing as enum category
                    $targetCategory = BookingStatusCategory::tryFrom($statusInput);
                    if ($targetCategory) {
                        $customStatus = BookingStatus::where('tenant_id', $tenant->id)
                            ->where('category', $targetCategory)
                            ->where('is_default', true)
                            ->first();
                    }
                }
            }
        }

        if ($targetCategory === null) {
            $msg = 'Status booking tujuan tidak valid.';
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $msg,
                    'error' => $msg,
                ], 422);
            }

            return back()->withErrors(['status' => $msg]);
        }

        try {
            $updated = $this->bookingService->transition($booking, $targetCategory, [
                'actor_id' => $request->user()?->id,
                'actor_type' => 'user',
                'source' => 'web',
                'reason' => $validated['reason'] ?? null,
                'status_id' => $customStatus?->id,
                'bypass_payment_guard' => (bool) ($validated['bypass_payment_guard'] ?? false),
            ]);

            $label = $customStatus instanceof BookingStatus ? $customStatus->name : $targetCategory->value;

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Status booking {$updated->code} berhasil diubah ke {$label}.",
                    'booking' => $updated->load(['customer', 'service', 'allocations.resource', 'status']),
                ]);
            }

            return back()->with('success', "Status booking {$updated->code} berhasil diperbarui.");
        } catch (BookingException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    /**
     * Reschedule booking to a new time with conflict detection & optimistic rollback (PRD 42, 213.1).
     */
    public function reschedule(Request $request, int $id): JsonResponse|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Booking $booking */
        $booking = Booking::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'new_start_at' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $rescheduled = $this->bookingService->reschedule(
                $booking,
                $validated['new_start_at'],
                [
                    'actor_id' => $request->user()?->id,
                    'actor_type' => 'user',
                    'source' => 'web',
                    'reason' => $validated['reason'] ?? null,
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Jadwal booking {$rescheduled->code} berhasil dipindahkan.",
                    'booking' => $rescheduled->load(['customer', 'service', 'allocations.resource']),
                ]);
            }

            return back()->with('success', "Jadwal booking {$rescheduled->code} berhasil dipindahkan.");
        } catch (BookingException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['new_start_at' => $e->getMessage()]);
        }
    }
}
