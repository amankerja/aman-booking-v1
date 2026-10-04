<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Booking\Services\BookingCodeGenerator;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use App\Support\Models\IdempotencyKey;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBooking
{
    public function __construct(
        protected BookingCodeGenerator $codeGenerator,
        protected BusinessCalendarService $calendarService
    ) {}

    /**
     * Create a booking with atomic anti-double-booking locks and idempotency.
     * PRD 195, 204.1, 204.3, 210, 214.
     *
     * @param  array{
     *     tenant: Tenant,
     *     service: Service,
     *     customer: array{name: string, phone: string, email?: string|null},
     *     start_at: CarbonInterface|string,
     *     quantity?: int,
     *     variant_id?: int|null,
     *     addon_ids?: array<int>,
     *     staff_id?: int|null,
     *     resource_ids?: array<int>,
     *     idempotency_key?: string|null,
     *     source?: string,
     *     requires_payment?: bool|null,
     *     actor_id?: int|null,
     *     actor_type?: string,
     * }  $data
     *
     * @throws BookingException
     */
    public function execute(array $data): Booking
    {
        /** @var Tenant $tenant */
        $tenant = $data['tenant'];
        /** @var Service $service */
        $service = $data['service'];

        // 1. Idempotency Check (PRD 204.3, 218)
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            /** @var Booking|null $existingBooking */
            $existingBooking = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingBooking) {
                /** @var Booking $loaded */
                $loaded = $existingBooking->load(['customer', 'allocations', 'service']);

                return $loaded;
            }

            /** @var IdempotencyKey|null $existingRecord */
            $existingRecord = IdempotencyKey::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('endpoint', 'booking.create')
                ->where('key', $idempotencyKey)
                ->first();

            if ($existingRecord && $existingRecord->response_snapshot !== null) {
                /** @var array<string, mixed> $snapshot */
                $snapshot = $existingRecord->response_snapshot;
                $bookingId = $snapshot['booking_id'] ?? null;
                if ($bookingId) {
                    /** @var Booking|null $cachedBooking */
                    $cachedBooking = Booking::withoutGlobalScopes()->find($bookingId);
                    if ($cachedBooking) {
                        /** @var Booking $loaded */
                        $loaded = $cachedBooking->load(['customer', 'allocations', 'service']);

                        return $loaded;
                    }
                }
            }
        }

        // 2. Validate Tenant & Subscription (PRD 210 point 5)
        if ($tenant->status === 'SUSPENDED' || $tenant->status === 'EXPIRED') {
            throw BookingException::tenantUnavailable();
        }

        // 3. Validate Service
        if ($service->archived_at !== null) {
            throw BookingException::validationFailed('Layanan yang dipilih sudah diarsipkan.');
        }

        // 4. Validate Policies (advance, horizon, business open)
        $business = $tenant->business()->first() ?? $tenant->businesses()->first();
        $tz = $business !== null ? $business->timezone : 'Asia/Jakarta';
        $startAtUtc = Carbon::parse($data['start_at'])->setTimezone('UTC');

        /** @var array<string, mixed> $rules */
        $rules = is_array($service->rules) ? $service->rules : [];
        /** @var array<string, mixed> $bSettings */
        $bSettings = (array) ($business !== null ? $business->settings : []);

        $minAdvanceHours = (int) ($rules['min_advance_hours'] ?? $bSettings['min_advance_hours'] ?? 0);
        if ($minAdvanceHours > 0) {
            if ($startAtUtc->lt(now()->addHours($minAdvanceHours))) {
                throw BookingException::minAdvanceNotMet("Booking minimal {$minAdvanceHours} jam sebelum jadwal.");
            }
        }

        $maxAdvanceDays = (int) ($rules['max_advance_days'] ?? $bSettings['max_advance_days'] ?? 0);
        if ($maxAdvanceDays > 0) {
            if ($startAtUtc->gt(now()->addDays($maxAdvanceDays))) {
                throw BookingException::beyondHorizon("Booking maksimal {$maxAdvanceDays} hari ke depan.");
            }
        }

        if ($business && ! $this->calendarService->isBusinessOpenAt($business, $startAtUtc)) {
            throw BookingException::outsideBusinessHours();
        }

        // 5. Calculate Duration, Buffer, Price & Quantities
        /** @var ServiceVariant|null $variant */
        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = ServiceVariant::where('tenant_id', $tenant->id)
                ->where('service_id', $service->id)
                ->find($data['variant_id']);
        }

        $baseDuration = $variant ? $variant->duration_minutes : $service->duration_minutes;
        $basePrice = $variant ? $variant->price_idr : $service->price_idr;

        $addonsData = [];
        $addonsDuration = 0;
        $addonsPrice = 0;
        if (! empty($data['addon_ids'])) {
            $addons = ServiceAddon::where('tenant_id', $tenant->id)
                ->where('service_id', $service->id)
                ->whereIn('id', $data['addon_ids'])
                ->get();

            foreach ($addons as $addon) {
                $addonsDuration += $addon->duration_minutes;
                $addonsPrice += $addon->price_idr;
                $addonsData[] = [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'duration_minutes' => $addon->duration_minutes,
                    'price_idr' => $addon->price_idr,
                ];
            }
        }

        $totalDuration = $baseDuration + $addonsDuration;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $totalPrice = ($basePrice + $addonsPrice) * $quantity;

        $bufferBefore = (int) ($service->buffer_before ?? 0);
        $bufferAfter = (int) ($service->buffer_after ?? 0);

        $endAtUtc = $startAtUtc->copy()->addMinutes($totalDuration);
        $occupiedStartUtc = $startAtUtc->copy()->subMinutes($bufferBefore);
        $occupiedEndUtc = $endAtUtc->copy()->addMinutes($bufferAfter);

        // 6. Resolve Customer
        $customerData = $data['customer'];
        $rawPhone = $customerData['phone'];
        $normalizedPhone = Customer::normalizePhone($rawPhone);
        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'phone_e164' => $normalizedPhone,
            ],
            [
                'name' => $customerData['name'],
                'email' => $customerData['email'] ?? null,
            ]
        );

        // 7. Resolve Resource IDs
        $resourceIds = $data['resource_ids'] ?? [];
        if (empty($resourceIds)) {
            if (! empty($data['staff_id'])) {
                $resourceIds[] = (int) $data['staff_id'];
            }

            // Resolve required resource rules
            $serviceRules = ServiceResourceRule::where('service_id', $service->id)
                ->where('is_required', true)
                ->get();

            foreach ($serviceRules as $sRule) {
                if ($sRule->resource_id && ! in_array($sRule->resource_id, $resourceIds, true)) {
                    $resourceIds[] = $sRule->resource_id;
                } elseif ($sRule->resource_type_id) {
                    $eligible = Resource::where('tenant_id', $tenant->id)
                        ->where('resource_type_id', $sRule->resource_type_id)
                        ->where('state', 'AVAILABLE')
                        ->whereNotIn('id', $resourceIds)
                        ->orderBy('id')
                        ->first();

                    if ($eligible) {
                        $resourceIds[] = $eligible->id;
                    }
                }
            }
        }

        // If still empty and service requires staff, pick eligible staff
        if (empty($resourceIds)) {
            $staff = Resource::where('tenant_id', $tenant->id)
                ->where('state', 'AVAILABLE')
                ->orderBy('id')
                ->first();

            if ($staff) {
                $resourceIds[] = $staff->id;
            }
        }

        // 8. Critical Transaction with Anti-Double-Booking Lock (PRD 204.1)
        return DB::transaction(function () use (
            $tenant,
            $service,
            $customer,
            $resourceIds,
            $startAtUtc,
            $endAtUtc,
            $occupiedStartUtc,
            $occupiedEndUtc,
            $bufferBefore,
            $bufferAfter,
            $totalDuration,
            $totalPrice,
            $quantity,
            $variant,
            $addonsData,
            $tz,
            $data,
            $idempotencyKey
        ) {
            // Lock resource rows in ascending ID order to prevent deadlocks (PRD 204.1)
            // Query: SELECT id FROM resources WHERE tenant_id = ? AND id IN (...) ORDER BY id FOR UPDATE
            $lockedResources = [];
            if (! empty($resourceIds)) {
                sort($resourceIds);
                $lockedResources = Resource::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('id', $resourceIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();
            }

            // Re-check conflict for each locked resource
            $serviceCapacity = (int) ($service->capacity ?? 1);
            foreach ($lockedResources as $resource) {
                // Check TimeBlocks
                $hasTimeBlock = TimeBlock::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->affectingResource($resource->id)
                    ->overlapping($occupiedStartUtc, $occupiedEndUtc)
                    ->exists();

                if ($hasTimeBlock) {
                    throw BookingException::resourceUnavailable("Resource {$resource->name} memiliki blok waktu pada jam tersebut.");
                }

                // Check active booking allocations
                $allocQuery = DB::table('booking_allocations')
                    ->where('tenant_id', $tenant->id)
                    ->where('resource_id', $resource->id)
                    ->where('status', 'ACTIVE')
                    ->where('start_at', '<', $occupiedEndUtc->toDateTimeString())
                    ->where('end_at', '>', $occupiedStartUtc->toDateTimeString());

                if ($serviceCapacity === 1) {
                    if ($allocQuery->exists()) {
                        throw BookingException::slotTaken("Resource {$resource->name} baru saja dipesan customer lain.");
                    }
                } else {
                    $currentBooked = (int) $allocQuery->sum('quantity');
                    if ($currentBooked + $quantity > $serviceCapacity) {
                        throw BookingException::capacityFull("Kuota kelas sudah penuh ({$currentBooked}/{$serviceCapacity}).");
                    }
                }
            }

            // Generate atomic sequential booking code (BK-YYYYMMDD-NNNNN)
            $startAtLocal = $startAtUtc->copy()->setTimezone($tz);
            $code = $this->codeGenerator->generate($tenant->id, $startAtLocal);

            // Snapshot Service & Pricing (PRD 144)
            $serviceSnapshot = [
                'service_id' => $service->id,
                'name' => $service->name,
                'price_idr' => $service->price_idr,
                'duration_minutes' => $totalDuration,
                'buffer_before' => $bufferBefore,
                'buffer_after' => $bufferAfter,
                'capacity' => $serviceCapacity,
                'variant' => $variant ? [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'duration_minutes' => $variant->duration_minutes,
                    'price_idr' => $variant->price_idr,
                ] : null,
                'addons' => $addonsData,
                'total_price_idr' => $totalPrice,
            ];

            // Determine initial status
            $requiresPayment = $data['requires_payment'] ?? false;
            $initialStatus = $requiresPayment ? BookingStatusCategory::PENDING : BookingStatusCategory::CONFIRMED;
            $holdExpiresAt = $requiresPayment ? now()->addMinutes(15) : null;

            $manageToken = Str::random(40);

            // Insert Booking
            /** @var Booking $booking */
            $booking = Booking::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'code' => $code,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'service_snapshot' => $serviceSnapshot,
                'start_at' => $startAtUtc,
                'end_at' => $endAtUtc,
                'business_timezone' => $tz,
                'status_category' => $initialStatus,
                'payment_status' => 'UNPAID',
                'total_idr' => $totalPrice,
                'deposit_idr' => 0,
                'source' => $data['source'] ?? 'PUBLIC',
                'hold_expires_at' => $holdExpiresAt,
                'reschedule_count' => 0,
                'manage_token' => hash('sha256', $manageToken),
                'manage_token_expires_at' => now()->addDays(30),
                'idempotency_key' => $idempotencyKey,
            ]);

            // Insert Allocations for each locked resource
            foreach ($lockedResources as $resource) {
                $role = ($resource->resourceType !== null && $resource->resourceType->is_staff) ? 'staff' : 'resource';
                BookingAllocation::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'booking_id' => $booking->id,
                    'resource_id' => $resource->id,
                    'role' => $role,
                    'start_at' => $occupiedStartUtc,
                    'end_at' => $occupiedEndUtc,
                    'status' => AllocationStatus::ACTIVE,
                    'quantity' => $quantity,
                ]);
            }

            // Insert Status History (PRD 213.2)
            BookingStatusHistory::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'booking_id' => $booking->id,
                'from_category' => null,
                'to_category' => $initialStatus->value,
                'actor_id' => $data['actor_id'] ?? null,
                'actor_type' => $data['actor_type'] ?? 'customer',
                'source' => $data['source'] ?? 'web',
                'reason' => 'Booking created',
            ]);

            // Record Audit Log (PRD 204.1)
            Audit::record([
                'tenant_id' => $tenant->id,
                'actor_id' => $data['actor_id'] ?? null,
                'actor_type' => $data['actor_type'] ?? 'customer',
                'action' => 'booking.create',
                'entity_type' => 'booking',
                'entity_id' => $booking->id,
                'after' => [
                    'code' => $booking->code,
                    'service' => $service->name,
                    'start_at' => $startAtUtc->toIso8601String(),
                    'total_idr' => $booking->total_idr,
                    'status' => $initialStatus->value,
                ],
                'source' => $data['source'] ?? 'web',
            ]);

            // Store Idempotency Key Snapshot (PRD 204.3)
            if ($idempotencyKey) {
                IdempotencyKey::withoutGlobalScopes()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'key' => $idempotencyKey,
                        'endpoint' => 'booking.create',
                    ],
                    [
                        'request_hash' => hash('sha256', json_encode($data) ?: ''),
                        'response_snapshot' => [
                            'booking_id' => $booking->id,
                            'code' => $booking->code,
                            'status' => $initialStatus->value,
                            'start_at' => $startAtUtc->toIso8601String(),
                            'total_idr' => $booking->total_idr,
                        ],
                        'status_code' => 201,
                        'expires_at' => now()->addHours(24),
                    ]
                );
            }

            /** @var Booking $loaded */
            $loaded = $booking->load(['customer', 'allocations', 'service']);

            return $loaded;
        });
    }
}
