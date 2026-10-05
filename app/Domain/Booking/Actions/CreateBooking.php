<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Models\BookingCustomField;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Booking\Services\BookingCodeGenerator;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Customer\Models\Customer;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Service\Services\ServiceDurationCalculator;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Audit;
use App\Support\Models\IdempotencyKey;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CreateBooking
{
    public function __construct(
        protected BookingCodeGenerator $codeGenerator,
        protected BusinessCalendarService $calendarService,
        protected ?ServiceDurationCalculator $durationCalculator = null
    ) {
        $this->durationCalculator = $durationCalculator ?? new ServiceDurationCalculator;
    }

    /**
     * Create a booking with atomic anti-double-booking locks and idempotency.
     * PRD 123, 130-133, 135, 164, 195, 199, 204.1, 204.3, 210, 214.
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
     *     custom_duration_minutes?: int|null,
     *     items?: array<int, array{service_id?: int|null, name?: string, duration_minutes: int, price_idr?: float|int, resource_id?: int|null}>,
     *     stages?: array<int, array{name: string, duration_minutes: int, buffer_before?: int, buffer_after?: int, resource_id?: int|null, role?: string|null}>,
     *     participants?: array<int, mixed>,
     *     idempotency_key?: string|null,
     *     source?: string,
     *     requires_payment?: bool|null,
     *     hold_minutes?: int|null,
     *     hold_expires_at?: CarbonInterface|string|null,
     *     status_category?: BookingStatusCategory|null,
     *     actor_id?: int|null,
     *     actor_type?: string,
     *     custom_fields?: array<int, array{field_key: string, field_label: string, field_type: string, value_text: string|null, value_json: array<string, mixed>|null}>|null,
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

        // 5. Resolve Customer
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

        // 6. Check Service Dependency (PRD 133)
        $prereqIds = [];
        if (! empty($rules['prerequisite_service_id'])) {
            $prereqIds[] = (int) $rules['prerequisite_service_id'];
        }
        if (! empty($rules['prerequisite_service_ids']) && is_array($rules['prerequisite_service_ids'])) {
            foreach ($rules['prerequisite_service_ids'] as $pid) {
                $prereqIds[] = (int) $pid;
            }
        }
        $prereqIds = array_values(array_unique(array_filter($prereqIds)));

        foreach ($prereqIds as $reqId) {
            $hasCompleted = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) use ($customer) {
                    $q->where('customer_id', $customer->id);
                    if ($customer->phone_e164) {
                        $q->orWhereHas('customer', fn ($cq) => $cq->where('phone_e164', $customer->phone_e164));
                    }
                })
                ->where('service_id', $reqId)
                ->where('status_category', BookingStatusCategory::COMPLETED->value)
                ->exists();

            if (! $hasCompleted) {
                $reqService = Service::withoutGlobalScopes()->find($reqId);
                $reqName = $reqService ? $reqService->name : "ID {$reqId}";
                throw BookingException::dependencyUnsatisfied("Layanan ini memerlukan penyelesaian layanan prasyarat '{$reqName}' terlebih dahulu.");
            }
        }

        // 7. Calculate Duration, Buffer, Price & Quantities (PRD 123, 130, 131, 135, 199)
        $participants = $data['participants'] ?? [];
        $participantCount = count($participants);
        $quantity = max(1, (int) ($data['quantity'] ?? ($participantCount > 0 ? $participantCount : 1)));

        /** @var ServiceVariant|null $variant */
        $variant = null;
        if (! empty($data['variant_id'])) {
            $variant = ServiceVariant::where('tenant_id', $tenant->id)
                ->where('service_id', $service->id)
                ->find($data['variant_id']);
        }

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

        // PRD 131, 132: Multi-Service Items
        $itemsData = [];
        $itemsDuration = 0;
        $itemsPrice = 0;
        if (! empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $iDuration = (int) $item['duration_minutes'];
                $iPrice = (float) ($item['price_idr'] ?? 0);
                $itemsDuration += $iDuration;
                $itemsPrice += $iPrice;
                $itemsData[] = [
                    'service_id' => $item['service_id'] ?? null,
                    'name' => $item['name'] ?? 'Item',
                    'duration_minutes' => $iDuration,
                    'price_idr' => $iPrice,
                    'resource_id' => $item['resource_id'] ?? null,
                ];
            }
        }

        if ($itemsDuration > 0) {
            $baseDuration = $itemsDuration;
            $basePrice = $itemsPrice;
        } else {
            $durationResult = $this->durationCalculator->calculate($service, [
                'variant_id' => $variant?->id,
                'addon_ids' => $data['addon_ids'] ?? [],
                'quantity' => $quantity,
                'custom_duration_minutes' => $data['custom_duration_minutes'] ?? null,
            ]);
            $baseDuration = (int) $durationResult['service_duration'];
            $basePrice = (float) ($variant ? $variant->price_idr : $service->price_idr);
        }

        // PRD 130: Sequential Service Stages
        /** @var array<int, array<string, mixed>> $stagesInput */
        $stagesInput = $data['stages'] ?? ($rules['stages'] ?? []);
        $stages = [];
        $stagesDurationSum = 0;
        if (! empty($stagesInput)) {
            $stageCursor = $startAtUtc->copy();
            foreach ($stagesInput as $idx => $stg) {
                $stgDur = max(1, (int) ($stg['duration_minutes'] ?? 15));
                $stgBufBefore = (int) ($stg['buffer_before'] ?? 0);
                $stgBufAfter = (int) ($stg['buffer_after'] ?? 0);
                $stgStart = $stageCursor->copy();
                $stgEnd = $stgStart->copy()->addMinutes($stgDur);
                $stgOccStart = $stgStart->copy()->subMinutes($stgBufBefore);
                $stgOccEnd = $stgEnd->copy()->addMinutes($stgBufAfter);

                $stages[] = [
                    'index' => $idx,
                    'name' => (string) ($stg['name'] ?? ('Stage '.($idx + 1))),
                    'duration_minutes' => $stgDur,
                    'buffer_before' => $stgBufBefore,
                    'buffer_after' => $stgBufAfter,
                    'start_at' => $stgStart,
                    'end_at' => $stgEnd,
                    'occupied_start_at' => $stgOccStart,
                    'occupied_end_at' => $stgOccEnd,
                    'resource_id' => isset($stg['resource_id']) ? (int) $stg['resource_id'] : null,
                    'role' => (string) ($stg['role'] ?? ($stg['name'] ?? 'stage')),
                ];
                $stagesDurationSum += $stgDur;
                $stageCursor = $stgEnd;
            }
        }

        $totalDuration = ! empty($stages) ? $stagesDurationSum : ($baseDuration + $addonsDuration);
        $totalPrice = ($basePrice + $addonsPrice) * $quantity;

        $bufferBefore = (int) ($service->buffer_before ?? 0);
        $bufferAfter = (int) ($service->buffer_after ?? 0);

        $endAtUtc = $startAtUtc->copy()->addMinutes($totalDuration);
        $occupiedStartUtc = $startAtUtc->copy()->subMinutes($bufferBefore);
        $occupiedEndUtc = $endAtUtc->copy()->addMinutes($bufferAfter);

        // 8. Resolve Resource IDs (PRD 128, 164)
        $resourceIds = $data['resource_ids'] ?? [];
        if (! empty($data['staff_id']) && ! in_array((int) $data['staff_id'], $resourceIds, true)) {
            $resourceIds[] = (int) $data['staff_id'];
        }
        foreach ($itemsData as $item) {
            if (! empty($item['resource_id']) && ! in_array((int) $item['resource_id'], $resourceIds, true)) {
                $resourceIds[] = (int) $item['resource_id'];
            }
        }
        foreach ($stages as $stg) {
            if (! empty($stg['resource_id']) && ! in_array((int) $stg['resource_id'], $resourceIds, true)) {
                $resourceIds[] = (int) $stg['resource_id'];
            }
        }

        // Resolve required resource rules
        $serviceRules = ServiceResourceRule::where('service_id', $service->id)
            ->where('is_required', true)
            ->get();

        foreach ($serviceRules as $sRule) {
            if ($sRule->resource_id && ! in_array($sRule->resource_id, $resourceIds, true)) {
                $resourceIds[] = $sRule->resource_id;
            } elseif ($sRule->resource_type_id) {
                $neededCount = max(1, (int) $sRule->quantity);
                $alreadyAssignedForType = Resource::where('tenant_id', $tenant->id)
                    ->where('resource_type_id', $sRule->resource_type_id)
                    ->whereIn('id', $resourceIds)
                    ->count();
                $stillNeeded = $neededCount - $alreadyAssignedForType;

                if ($stillNeeded > 0) {
                    $eligibles = Resource::where('tenant_id', $tenant->id)
                        ->where('resource_type_id', $sRule->resource_type_id)
                        ->where('state', 'AVAILABLE')
                        ->whereNotIn('id', $resourceIds)
                        ->orderBy('id')
                        ->take($stillNeeded)
                        ->pluck('id')
                        ->all();

                    foreach ($eligibles as $eid) {
                        $resourceIds[] = $eid;
                    }
                }
            }
        }

        // If still empty and no stages defined, pick eligible staff
        if (empty($resourceIds) && empty($stages)) {
            $staff = Resource::where('tenant_id', $tenant->id)
                ->where('state', 'AVAILABLE')
                ->orderBy('id')
                ->first();

            if ($staff) {
                $resourceIds[] = $staff->id;
            }
        }

        // 9. Critical Transaction with Anti-Double-Booking Lock (PRD 204.1)
        $createdBooking = DB::transaction(function () use (
            $tenant,
            $service,
            $customer,
            $resourceIds,
            $stages,
            $itemsData,
            $participants,
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
            $idempotencyKey,
            $rules,
            $bSettings
        ) {
            // Lock resource rows in ascending ID order to prevent deadlocks (PRD 204.1)
            $lockedResources = collect();
            if (! empty($resourceIds)) {
                $uniqueIds = array_values(array_unique($resourceIds));
                sort($uniqueIds);
                $lockedResources = Resource::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('id', $uniqueIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            }

            $serviceCapacity = (int) ($service->capacity ?? 1);
            $stageResourceIds = array_filter(array_column($stages, 'resource_id'));
            $generalResources = $lockedResources->reject(fn ($r) => in_array($r->id, $stageResourceIds, true));

            if (! empty($stages)) {
                // PRD 130: Stage-specific conflict checking
                foreach ($stages as $stg) {
                    if (! empty($stg['resource_id'])) {
                        /** @var Resource|null $stageRes */
                        $stageRes = $lockedResources->get($stg['resource_id']);
                        if (! $stageRes) {
                            throw BookingException::resourceUnavailable("Resource ID {$stg['resource_id']} untuk tahap '{$stg['name']}' tidak ditemukan.");
                        }

                        // Check TimeBlocks for stage window
                        $hasTimeBlock = TimeBlock::withoutGlobalScopes()
                            ->where('tenant_id', $tenant->id)
                            ->affectingResource($stageRes->id)
                            ->overlapping($stg['occupied_start_at'], $stg['occupied_end_at'])
                            ->exists();

                        if ($hasTimeBlock) {
                            throw BookingException::resourceUnavailable("Resource {$stageRes->name} untuk tahap '{$stg['name']}' memiliki blok waktu pada jam tersebut.");
                        }

                        // Check active booking allocations for stage window
                        $stageAllocConflict = DB::table('booking_allocations')
                            ->join('bookings', 'bookings.id', '=', 'booking_allocations.booking_id')
                            ->where('booking_allocations.tenant_id', $tenant->id)
                            ->where('booking_allocations.resource_id', $stageRes->id)
                            ->where('booking_allocations.status', 'ACTIVE')
                            ->where('booking_allocations.start_at', '<', $stg['occupied_end_at']->toDateTimeString())
                            ->where('booking_allocations.end_at', '>', $stg['occupied_start_at']->toDateTimeString())
                            ->where(function ($q) {
                                $q->where('bookings.status_category', '!=', 'PENDING')
                                    ->orWhereNull('bookings.hold_expires_at')
                                    ->orWhere('bookings.hold_expires_at', '>', now()->toDateTimeString());
                            })
                            ->exists();

                        if ($stageAllocConflict) {
                            throw BookingException::slotTaken("Resource {$stageRes->name} untuk tahap '{$stg['name']}' tidak tersedia pada jam tersebut.");
                        }
                    }
                }

                // Check general resources over entire booking duration
                foreach ($generalResources as $resource) {
                    $hasTimeBlock = TimeBlock::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->affectingResource($resource->id)
                        ->overlapping($occupiedStartUtc, $occupiedEndUtc)
                        ->exists();

                    if ($hasTimeBlock) {
                        throw BookingException::resourceUnavailable("Resource {$resource->name} memiliki blok waktu pada jam tersebut.");
                    }

                    $allocConflict = DB::table('booking_allocations')
                        ->join('bookings', 'bookings.id', '=', 'booking_allocations.booking_id')
                        ->where('booking_allocations.tenant_id', $tenant->id)
                        ->where('booking_allocations.resource_id', $resource->id)
                        ->where('booking_allocations.status', 'ACTIVE')
                        ->where('booking_allocations.start_at', '<', $occupiedEndUtc->toDateTimeString())
                        ->where('booking_allocations.end_at', '>', $occupiedStartUtc->toDateTimeString())
                        ->where(function ($q) {
                            $q->where('bookings.status_category', '!=', 'PENDING')
                                ->orWhereNull('bookings.hold_expires_at')
                                ->orWhere('bookings.hold_expires_at', '>', now()->toDateTimeString());
                        })
                        ->exists();

                    if ($allocConflict) {
                        throw BookingException::slotTaken("Resource {$resource->name} baru saja dipesan customer lain.");
                    }
                }
            } else {
                // Re-check conflict for each locked resource (PRD 128, 164, 204.1)
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

                    // Check active booking allocations, ignoring expired holds (PRD 139, 210 point 4)
                    $allocQuery = DB::table('booking_allocations')
                        ->join('bookings', 'bookings.id', '=', 'booking_allocations.booking_id')
                        ->where('booking_allocations.tenant_id', $tenant->id)
                        ->where('booking_allocations.resource_id', $resource->id)
                        ->where('booking_allocations.status', 'ACTIVE')
                        ->where('booking_allocations.start_at', '<', $occupiedEndUtc->toDateTimeString())
                        ->where('booking_allocations.end_at', '>', $occupiedStartUtc->toDateTimeString())
                        ->where(function ($q) {
                            $q->where('bookings.status_category', '!=', 'PENDING')
                                ->orWhereNull('bookings.hold_expires_at')
                                ->orWhere('bookings.hold_expires_at', '>', now()->toDateTimeString());
                        });

                    if ($serviceCapacity === 1) {
                        if ($allocQuery->exists()) {
                            throw BookingException::slotTaken("Resource {$resource->name} baru saja dipesan customer lain.");
                        }
                    } else {
                        $currentBooked = (int) $allocQuery->sum('booking_allocations.quantity');
                        if ($currentBooked + $quantity > $serviceCapacity) {
                            throw BookingException::capacityFull("Kuota kelas sudah penuh ({$currentBooked}/{$serviceCapacity}).");
                        }
                    }
                }
            }

            // Generate atomic sequential booking code (BK-YYYYMMDD-NNNNN)
            $startAtLocal = $startAtUtc->copy()->setTimezone($tz);
            $code = $this->codeGenerator->generate($tenant->id, $startAtLocal);

            $formattedStages = array_map(function ($s) {
                return [
                    'name' => $s['name'],
                    'duration_minutes' => $s['duration_minutes'],
                    'start_at' => $s['start_at']->toIso8601String(),
                    'end_at' => $s['end_at']->toIso8601String(),
                    'resource_id' => $s['resource_id'],
                    'role' => $s['role'],
                ];
            }, $stages);

            // Snapshot Service & Pricing (PRD 144, 131, 132, 135)
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
                'items' => $itemsData,
                'stages' => $formattedStages,
                'participants' => $participants,
                'total_price_idr' => $totalPrice,
            ];

            // Determine initial status and reservation hold (PRD 139, 210 point 4)
            $requiresPayment = (bool) ($data['requires_payment'] ?? false);
            $initialStatus = ($data['status_category'] ?? null) instanceof BookingStatusCategory
                ? $data['status_category']
                : ($requiresPayment ? BookingStatusCategory::PENDING : BookingStatusCategory::CONFIRMED);

            /** @var array<string, mixed> $pGateway */
            $pGateway = is_array($bSettings['payment_gateway'] ?? null) ? $bSettings['payment_gateway'] : [];
            $holdDurationMinutes = (int) ($data['hold_minutes']
                ?? $rules['hold_minutes']
                ?? $bSettings['hold_duration_minutes']
                ?? ($pGateway['hold_duration_minutes'] ?? 10));

            $holdExpiresAt = null;
            if ($requiresPayment || $initialStatus === BookingStatusCategory::PENDING) {
                if (! empty($data['hold_expires_at'])) {
                    $holdExpiresAt = Carbon::parse($data['hold_expires_at'])->setTimezone('UTC');
                } else {
                    $holdExpiresAt = now()->addMinutes(max(1, $holdDurationMinutes));
                }
            }

            $manageToken = Str::random(40);

            // Bind to published workflow version (PRD 23, 204.5)
            /** @var \App\Domain\Workflow\Models\Workflow|null $activeWorkflow */
            $activeWorkflow = \App\Domain\Workflow\Models\Workflow::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->first()
                ?? \App\Domain\Workflow\Models\Workflow::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->first();
            $workflowVersionId = $activeWorkflow?->current_version_id;

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
                'workflow_version_id' => $workflowVersionId,
                'idempotency_key' => $idempotencyKey,
            ]);

            $booking->raw_manage_token = $manageToken;

            // Insert Allocations (PRD 130, 204.1)
            if (! empty($stages)) {
                foreach ($stages as $stg) {
                    if (! empty($stg['resource_id'])) {
                        /** @var Resource|null $stgRes */
                        $stgRes = $lockedResources->get($stg['resource_id']);
                        $stgRole = $stg['role'] ?: ($stgRes?->resourceType?->is_staff ? 'staff' : 'resource');

                        BookingAllocation::withoutGlobalScopes()->create([
                            'tenant_id' => $tenant->id,
                            'booking_id' => $booking->id,
                            'resource_id' => $stg['resource_id'],
                            'role' => $stgRole,
                            'start_at' => $stg['occupied_start_at'],
                            'end_at' => $stg['occupied_end_at'],
                            'status' => AllocationStatus::ACTIVE,
                            'quantity' => $quantity,
                        ]);
                    }
                }

                foreach ($generalResources as $resource) {
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
            } else {
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
            }

            // Reserve or prepare stock for consumable inventory items (PRD 17.3, Phase 4.4)
            app(\App\Domain\Inventory\Services\InventoryService::class)->reserveOrDeductForBooking($booking);

            // Save custom field responses (PRD 214)
            if (! empty($data['custom_fields'])) {
                foreach ($data['custom_fields'] as $cf) {
                    BookingCustomField::withoutGlobalScopes()->create([
                        'tenant_id' => $tenant->id,
                        'booking_id' => $booking->id,
                        'field_key' => $cf['field_key'],
                        'field_label' => $cf['field_label'],
                        'field_type' => $cf['field_type'],
                        'value_text' => $cf['value_text'] ?? null,
                        'value_json' => $cf['value_json'] ?? null,
                    ]);
                }
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

        // Non-blocking notification dispatch (Phase 2.4)
        try {
            app(NotificationService::class)->dispatchBookingNotification(
                $createdBooking,
                NotificationEvent::BOOKING_CREATED,
                $createdBooking->raw_manage_token
            );
        } catch (Throwable $e) {
            Log::warning('Notification dispatch failed in CreateBooking: '.$e->getMessage());
        }

        // Fire domain event for Workflow Runner (PRD 62, 204.5)
        try {
            event(new \App\Domain\Booking\Events\BookingCreated($createdBooking));
        } catch (Throwable $e) {
            Log::warning('BookingCreated event dispatch failed in CreateBooking: '.$e->getMessage());
        }

        return $createdBooking;
    }
}
