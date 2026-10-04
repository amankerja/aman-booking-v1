<?php

namespace App\Domain\Availability\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Models\CalendarException;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Resource\Services\ResourceAvailabilityService;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Services\ServiceDurationCalculator;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @phpstan-type SlotArray array{
 *     date: string,
 *     start_time: string,
 *     end_time: string,
 *     start_at: string,
 *     end_at: string,
 *     duration_minutes: int,
 *     buffer_before_minutes: int,
 *     buffer_after_minutes: int,
 *     total_occupied_minutes: int,
 *     capacity: int,
 *     booked_quantity: int,
 *     available_capacity: int,
 *     is_available: bool,
 *     reason_code: string|null,
 *     reason_message: string|null,
 *     available_staff_ids: array<int>,
 *     available_staff: array<int, array{id: int, name: string}>,
 *     available_resource_ids: array<int>,
 *     available_resources: array<int, array{id: int, name: string, type: string|null}>
 * }
 *
 * Single Source of Truth for availability calculation across AMAN BOOKING.
 *
 * Evaluates interval intersections:
 * (Business Hours ∩ Service Schedule ∩ Staff/Resource Schedule)
 * - (Active Bookings + TimeBlocks/Cuti + Holidays/Blackout + Breaks)
 *
 * Adheres strictly to PRD 19, 20, 124, 128, 129, 134, 135, 137, 164, 165, 197, 198, 204.2, and 218.
 */
class AvailabilityService
{
    public const REASON_SLOT_TAKEN = 'SLOT_TAKEN';

    public const REASON_OUTSIDE_BUSINESS_HOURS = 'OUTSIDE_BUSINESS_HOURS';

    public const REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION = 'RESOURCE_UNAVAILABLE_FOR_FULL_DURATION';

    public const REASON_CAPACITY_FULL = 'CAPACITY_FULL';

    public const REASON_MIN_ADVANCE_NOT_MET = 'MIN_ADVANCE_NOT_MET';

    public const REASON_BEYOND_BOOKING_HORIZON = 'BEYOND_BOOKING_HORIZON';

    /**
     * Friendly user messages per PRD 218.
     *
     * @var array<string, string>
     */
    public const REASON_MESSAGES = [
        self::REASON_SLOT_TAKEN => 'Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.',
        self::REASON_OUTSIDE_BUSINESS_HOURS => 'Waktu yang dipilih di luar jam operasional.',
        self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION => 'Terapis/ruangan tidak tersedia untuk durasi penuh pada waktu tersebut.',
        self::REASON_CAPACITY_FULL => 'Kuota untuk jadwal ini sudah penuh. Anda dapat bergabung ke daftar tunggu bila tersedia.',
        self::REASON_MIN_ADVANCE_NOT_MET => 'Booking minimal dilakukan sekian jam sebelum jadwal.',
        self::REASON_BEYOND_BOOKING_HORIZON => 'Jadwal yang dipilih terlalu jauh dari hari ini.',
    ];

    public function __construct(
        protected ?BusinessCalendarService $calendarService = null,
        protected ?ServiceDurationCalculator $durationCalculator = null,
        protected ?ResourceAvailabilityService $resourceService = null
    ) {
        $this->calendarService = $calendarService ?? new BusinessCalendarService;
        $this->durationCalculator = $durationCalculator ?? new ServiceDurationCalculator;
        $this->resourceService = $resourceService ?? new ResourceAvailabilityService;
    }

    /**
     * Calculate all slot candidates for a given single date.
     *
     * @param  array{
     *     preferred_staff_id?: int|null,
     *     staff_id?: int|null,
     *     quantity?: int,
     *     addon_ids?: array<int>,
     *     variant_id?: int|null,
     *     custom_duration_minutes?: int|null,
     *     slot_step_minutes?: int|null,
     *     existing_allocations?: array<int, array{
     *         resource_id: int,
     *         start_at: CarbonInterface|string,
     *         end_at: CarbonInterface|string,
     *         buffer_before?: int,
     *         buffer_after?: int,
     *         quantity?: int,
     *         service_id?: int|null,
     *         status?: string
     *     }>,
     *     include_unavailable?: bool
     * }  $options
     * @return Collection<int, SlotArray>
     */
    public function getSlotsForDate(
        Tenant $tenant,
        Service $service,
        CarbonInterface|string $date,
        array $options = []
    ): Collection {
        // 1. Resolve business & timezone
        /** @var Business|null $business */
        $business = $tenant->business ?? Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $timezone = $business?->timezone ?: 'Asia/Jakarta';

        $localDate = is_string($date) ? Carbon::parse($date, $timezone) : Carbon::instance($date)->setTimezone($timezone);
        $dateString = $localDate->format('Y-m-d');
        $dayOfWeek = (int) $localDate->dayOfWeek;

        // 2. Compute effective duration and buffers
        $durationResult = $this->durationCalculator->calculate($service, [
            'variant_id' => $options['variant_id'] ?? null,
            'addon_ids' => $options['addon_ids'] ?? [],
            'quantity' => $options['quantity'] ?? 1,
            'custom_duration_minutes' => $options['custom_duration_minutes'] ?? null,
        ]);
        $durationMinutes = (int) $durationResult['total_service_duration'];
        $bufferBefore = (int) $durationResult['buffer_before'];
        $bufferAfter = (int) $durationResult['buffer_after'];
        $totalOccupiedMinutes = (int) $durationResult['total_occupied_duration'];

        // 3. Resolve capacity & requested quantity
        $serviceCapacity = (int) ($service->capacity ?: 1);
        $requestedQuantity = max(1, (int) ($options['quantity'] ?? 1));

        // 4. Resolve step size
        $stepMinutes = (int) ($options['slot_step_minutes'] ?? 30);
        if ($stepMinutes <= 0) {
            $stepMinutes = 30;
        }

        // 5. Resolve business hours & calendar exceptions
        /** @var CalendarException|null $exception */
        $exception = $business ? CalendarException::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereDate('date', $dateString)
            ->first() : null;

        /** @var BusinessHour|null $businessHour */
        $businessHour = $business ? BusinessHour::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('day_of_week', $dayOfWeek)
            ->first() : null;

        $isClosed = false;
        $openTimeStr = $businessHour?->open_time ?: '09:00:00';
        $closeTimeStr = $businessHour?->close_time ?: '18:00:00';
        /** @var array<int, array<string, string>> $businessBreaks */
        $businessBreaks = $businessHour?->breaks ?: [];

        if ($exception) {
            if ($exception->is_closed) {
                $isClosed = true;
            } elseif ($exception->open_time && $exception->close_time) {
                $openTimeStr = $exception->open_time;
                $closeTimeStr = $exception->close_time;
            }
        } elseif (! $businessHour || ! $businessHour->is_open) {
            $isClosed = true;
        }

        $businessOpenAt = Carbon::parse("{$dateString} {$openTimeStr}", $timezone);
        $businessCloseAt = Carbon::parse("{$dateString} {$closeTimeStr}", $timezone);

        // 6. Pre-fetch tenant resources & service resource rules
        /** @var Collection<int, \App\Domain\Resource\Models\Resource> $allTenantResources */
        $allTenantResources = Resource::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('archived_at')
            ->where('state', 'AVAILABLE')
            ->with(['resourceType', 'schedules', 'group'])
            ->get();

        $rules = ServiceResourceRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('service_id', $service->id)
            ->get();

        $preferredStaffId = $options['preferred_staff_id'] ?? $options['staff_id'] ?? null;
        $preferredStaffIdInt = $preferredStaffId !== null ? (int) $preferredStaffId : null;

        // If no rules exist on service, build default staff rule
        if ($rules->isEmpty()) {
            $defaultRule = new ServiceResourceRule;
            $defaultRule->is_required = true;
            $defaultRule->quantity = 1;
            $defaultRule->assignment_mode = ServiceResourceRule::MODE_AUTO_ASSIGN;
            $defaultRule->setAttribute('is_default_staff_rule', true);
            $rules = collect([$defaultRule]);
        }

        // 7. Generate candidate slots
        /** @var Collection<int, SlotArray> $slots */
        $slots = collect();

        // If the business is closed for the entire day (holiday / blackout / closed day of week)
        if ($isClosed) {
            $cursor = $businessOpenAt->copy();
            while ($cursor->lt($businessCloseAt)) {
                $slotStart = $cursor->copy();
                $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => 0,
                    'available_capacity' => 0,
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                    'available_resource_ids' => [],
                    'available_resources' => [],
                ]);

                $cursor->addMinutes($stepMinutes);
            }

            if (! ($options['include_unavailable'] ?? true)) {
                return $slots->where('is_available', true)->values();
            }

            return $slots;
        }

        // Parse business breaks
        $parsedBusinessBreaks = [];
        foreach ($businessBreaks as $break) {
            $bStartStr = $break['start_time'] ?? $break['start'] ?? null;
            $bEndStr = $break['end_time'] ?? $break['end'] ?? null;
            if ($bStartStr && $bEndStr) {
                $parsedBusinessBreaks[] = [
                    'start' => Carbon::parse("{$dateString} {$bStartStr}", $timezone),
                    'end' => Carbon::parse("{$dateString} {$bEndStr}", $timezone),
                ];
            }
        }

        // Loop through candidate start times
        $cursor = $businessOpenAt->copy();
        while ($cursor->lt($businessCloseAt)) {
            $slotStart = $cursor->copy();
            $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

            // PRD 20 / 124: Calculate occupied window with buffer
            $occupiedStart = $slotStart->copy()->subMinutes($bufferBefore);
            $occupiedEnd = $slotEnd->copy()->addMinutes($bufferAfter);

            // Check A: Does appointment slot finish within business operating window?
            if ($slotEnd->gt($businessCloseAt) || $slotStart->lt($businessOpenAt)) {
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => 0,
                    'available_capacity' => 0,
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                    'available_resource_ids' => [],
                    'available_resources' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            // Check B: Does appointment slot overlap with any business breaks?
            $overlapsBusinessBreak = false;
            foreach ($parsedBusinessBreaks as $b) {
                if ($slotStart->lt($b['end']) && $slotEnd->gt($b['start'])) {
                    $overlapsBusinessBreak = true;
                    break;
                }
            }

            if ($overlapsBusinessBreak) {
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => 0,
                    'available_capacity' => 0,
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                    'available_resource_ids' => [],
                    'available_resources' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            // Check C: Capacity model (PRD 134, 135)
            $bookedQuantity = 0;
            $fixtureAllocations = $options['existing_allocations'] ?? [];
            foreach ($fixtureAllocations as $alloc) {
                $allocServiceId = $alloc['service_id'] ?? null;
                if ($allocServiceId === null || (int) $allocServiceId === $service->id) {
                    $allocStart = Carbon::parse($alloc['start_at'], $timezone);
                    $allocEnd = Carbon::parse($alloc['end_at'], $timezone);

                    if ($slotStart->lt($allocEnd) && $slotEnd->gt($allocStart)) {
                        $bookedQuantity += (int) ($alloc['quantity'] ?? 1);
                    }
                }
            }

            if (Schema::hasTable('booking_allocations')) {
                $dbBooked = (int) DB::table('booking_allocations')
                    ->where('start_at', '<', $slotEnd->toDateTimeString())
                    ->where('end_at', '>', $slotStart->toDateTimeString())
                    ->sum('quantity');
                $bookedQuantity += $dbBooked;
            }

            $availableCapacity = max(0, $serviceCapacity - $bookedQuantity);

            if ($serviceCapacity > 1 && $requestedQuantity > $availableCapacity) {
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => $bookedQuantity,
                    'available_capacity' => $availableCapacity,
                    'is_available' => false,
                    'reason_code' => self::REASON_CAPACITY_FULL,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_CAPACITY_FULL],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                    'available_resource_ids' => [],
                    'available_resources' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            // Check D: Multi-Resource & Parallel Resource Rules (PRD 128, 129, 164, 165)
            $allRequiredSatisfied = true;
            /** @var Collection<int, \App\Domain\Resource\Models\Resource> $slotSelectedResources */
            $slotSelectedResources = collect();
            $slotFailureReason = null;

            foreach ($rules as $rule) {
                /** @var Collection<int, \App\Domain\Resource\Models\Resource> $candidates */
                $candidates = $allTenantResources;

                if (! empty($rule->getAttribute('is_default_staff_rule'))) {
                    $candidates = $candidates->filter(fn (Resource $r) => $r->resourceType !== null ? $r->resourceType->is_staff : true);
                } else {
                    if ($rule->resource_id) {
                        $candidates = $candidates->where('id', $rule->resource_id);
                    }
                    if ($rule->resource_type_id) {
                        $candidates = $candidates->where('resource_type_id', $rule->resource_type_id);
                    }
                    if ($rule->group_id) {
                        $candidates = $candidates->where('group_id', $rule->group_id);
                    }
                    if (! empty($rule->required_skills)) {
                        $candidates = $candidates->filter(fn (Resource $r) => $r->hasAllSkills($rule->required_skills));
                    }
                }

                // If customer requested a preferred staff and preferred staff matches candidates
                if ($preferredStaffIdInt !== null && $candidates->contains('id', $preferredStaffIdInt)) {
                    $candidates = $candidates->where('id', $preferredStaffIdInt);
                } elseif ($preferredStaffIdInt !== null && (! empty($rule->getAttribute('is_default_staff_rule')) || ($rule->resourceType && $rule->resourceType->is_staff))) {
                    // Preferred staff requested, but was not among qualified candidates for this staff rule
                    $candidates = collect();
                }

                // Exclude resources already selected by a preceding rule in this same slot
                $alreadySelectedIds = $slotSelectedResources->pluck('id')->all();
                $candidates = $candidates->whereNotIn('id', $alreadySelectedIds);

                // Evaluate availability for each candidate resource for the occupied window
                /** @var Collection<int, \App\Domain\Resource\Models\Resource> $freeCandidates */
                $freeCandidates = collect();
                $ruleBookingConflict = false;

                foreach ($candidates as $cand) {
                    $hasConflict = false;
                    $isAvailable = $this->isResourceAvailableForWindow(
                        $cand,
                        $occupiedStart,
                        $occupiedEnd,
                        $dayOfWeek,
                        $dateString,
                        $timezone,
                        $options,
                        $hasConflict,
                        $serviceCapacity
                    );

                    if ($hasConflict) {
                        $ruleBookingConflict = true;
                    }

                    if ($isAvailable) {
                        $freeCandidates->push($cand);
                    }
                }

                $requiredQty = max(1, (int) $rule->quantity);

                if ($freeCandidates->count() >= $requiredQty) {
                    $slotSelectedResources = $slotSelectedResources->merge($freeCandidates);
                } else {
                    if ($rule->is_required) {
                        $allRequiredSatisfied = false;
                        $slotFailureReason = $ruleBookingConflict
                            ? self::REASON_SLOT_TAKEN
                            : self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION;
                        break;
                    }
                    $slotSelectedResources = $slotSelectedResources->merge($freeCandidates);
                }
            }

            if ($allRequiredSatisfied && $slotSelectedResources->isNotEmpty()) {
                /** @var Collection<int, \App\Domain\Resource\Models\Resource> $staffResources */
                $staffResources = $slotSelectedResources->filter(fn (Resource $r) => $r->resourceType === null || $r->resourceType->is_staff);

                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => $bookedQuantity,
                    'available_capacity' => $availableCapacity,
                    'is_available' => true,
                    'reason_code' => null,
                    'reason_message' => null,
                    'available_staff_ids' => $staffResources->pluck('id')->values()->all(),
                    'available_staff' => $staffResources->map(fn (Resource $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                    ])->values()->all(),
                    'available_resource_ids' => $slotSelectedResources->pluck('id')->unique()->values()->all(),
                    'available_resources' => $slotSelectedResources->unique('id')->map(fn (Resource $r) => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'type' => $r->resourceType?->code,
                    ])->values()->all(),
                ]);
            } else {
                $reasonCode = $slotFailureReason ?? self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION;

                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'buffer_before_minutes' => $bufferBefore,
                    'buffer_after_minutes' => $bufferAfter,
                    'total_occupied_minutes' => $totalOccupiedMinutes,
                    'capacity' => $serviceCapacity,
                    'booked_quantity' => $bookedQuantity,
                    'available_capacity' => $availableCapacity,
                    'is_available' => false,
                    'reason_code' => $reasonCode,
                    'reason_message' => self::REASON_MESSAGES[$reasonCode],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                    'available_resource_ids' => [],
                    'available_resources' => [],
                ]);
            }

            $cursor->addMinutes($stepMinutes);
        }

        if (! ($options['include_unavailable'] ?? true)) {
            return $slots->where('is_available', true)->values();
        }

        return $slots;
    }

    /**
     * Check if a resource is available for the given occupied window.
     *
     * @param  array<string, mixed>  $options
     */
    protected function isResourceAvailableForWindow(
        Resource $resource,
        CarbonInterface $occupiedStart,
        CarbonInterface $occupiedEnd,
        int $dayOfWeek,
        string $dateString,
        string $timezone,
        array $options,
        ?bool &$bookingConflict = null,
        int $serviceCapacity = 1
    ): bool {
        $bookingConflict = false;

        // 1. Weekly schedule check
        /** @var ResourceSchedule|null $schedule */
        $schedule = ResourceSchedule::withoutGlobalScopes()
            ->where('tenant_id', $resource->tenant_id)
            ->where('resource_id', $resource->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if ($schedule) {
            if (! $schedule->is_available) {
                return false;
            }

            $shiftStart = Carbon::parse("{$dateString} {$schedule->start_time}", $timezone);
            $shiftEnd = Carbon::parse("{$dateString} {$schedule->end_time}", $timezone);

            // PRD 197 / 198: Does occupied window exceed shift boundaries?
            if ($occupiedStart->lt($shiftStart) || $occupiedEnd->gt($shiftEnd)) {
                return false;
            }

            // Staff / resource breaks
            if (! empty($schedule->breaks)) {
                /** @var array<int, array{start?: string, end?: string, title?: string}> $rawBreaks */
                $rawBreaks = $schedule->breaks;
                foreach ($rawBreaks as $break) {
                    $bStartStr = $break['start'] ?? null;
                    $bEndStr = $break['end'] ?? null;

                    if ($bStartStr && $bEndStr) {
                        $bStart = Carbon::parse("{$dateString} {$bStartStr}", $timezone);
                        $bEnd = Carbon::parse("{$dateString} {$bEndStr}", $timezone);

                        if ($occupiedStart->lt($bEnd) && $occupiedEnd->gt($bStart)) {
                            return false;
                        }
                    }
                }
            }
        }

        // 2. TimeBlock (leave, cuti, maintenance, manual block)
        $hasTimeBlock = TimeBlock::withoutGlobalScopes()
            ->where('tenant_id', $resource->tenant_id)
            ->affectingResource($resource->id)
            ->overlapping($occupiedStart, $occupiedEnd)
            ->exists();

        if ($hasTimeBlock) {
            return false;
        }

        // 3. Existing allocations / bookings conflict check (PRD 19, PRD 20, PRD 165)
        $fixtureAllocations = $options['existing_allocations'] ?? [];
        if (! empty($fixtureAllocations)) {
            foreach ($fixtureAllocations as $alloc) {
                if ((int) $alloc['resource_id'] === $resource->id) {
                    // For capacity-based services, capacity usage is handled in Check C
                    if ($serviceCapacity > 1 && isset($alloc['quantity'])) {
                        continue;
                    }

                    $allocStart = Carbon::parse($alloc['start_at'], $timezone);
                    $allocEnd = Carbon::parse($alloc['end_at'], $timezone);

                    // Check if allocation itself has buffer
                    $bBefore = (int) ($alloc['buffer_before'] ?? 0);
                    $bAfter = (int) ($alloc['buffer_after'] ?? 0);
                    $allocOccupiedStart = $allocStart->copy()->subMinutes($bBefore);
                    $allocOccupiedEnd = $allocEnd->copy()->addMinutes($bAfter);

                    if ($occupiedStart->lt($allocOccupiedEnd) && $occupiedEnd->gt($allocOccupiedStart)) {
                        $bookingConflict = true;

                        return false;
                    }
                }
            }
        }

        if (Schema::hasTable('booking_allocations') && $serviceCapacity === 1) {
            $hasDbAllocation = DB::table('booking_allocations')
                ->where('resource_id', $resource->id)
                ->where('start_at', '<', $occupiedEnd->toDateTimeString())
                ->where('end_at', '>', $occupiedStart->toDateTimeString())
                ->exists();

            if ($hasDbAllocation) {
                $bookingConflict = true;

                return false;
            }
        }

        return true;
    }

    /**
     * Backward-compatible helper for staff check.
     *
     * @param  array<string, mixed>  $options
     */
    protected function isStaffAvailableForWindow(
        Resource $staff,
        CarbonInterface $slotStart,
        CarbonInterface $slotEnd,
        int $dayOfWeek,
        string $dateString,
        string $timezone,
        array $options,
        ?bool &$bookingConflict = null
    ): bool {
        return $this->isResourceAvailableForWindow(
            $staff,
            $slotStart,
            $slotEnd,
            $dayOfWeek,
            $dateString,
            $timezone,
            $options,
            $bookingConflict
        );
    }

    /**
     * Resolve all qualified staff for the service and optional preferred staff.
     *
     * @return Collection<int, \App\Domain\Resource\Models\Resource>
     */
    public function getEligibleStaff(Tenant $tenant, Service $service, ?int $preferredStaffId = null): Collection
    {
        // Fetch all service resource rules
        $rules = ServiceResourceRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('service_id', $service->id)
            ->get();

        /** @var Collection<int, \App\Domain\Resource\Models\Resource> $staffQuery */
        $staffQuery = Resource::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNull('archived_at')
            ->where('state', 'AVAILABLE')
            ->with(['resourceType', 'schedules', 'group'])
            ->get();

        // Filter by resource rules if configured
        if ($rules->isNotEmpty()) {
            /** @var array<string> $requiredSkills */
            $requiredSkills = $rules->pluck('required_skills')
                ->filter()
                ->flatten()
                ->unique()
                ->values()
                ->all();

            $specificResourceIds = $rules->pluck('resource_id')->filter()->all();
            $typeIds = $rules->pluck('resource_type_id')->filter()->all();
            $groupIds = $rules->pluck('group_id')->filter()->all();

            $staffQuery = $staffQuery->filter(function (Resource $resource) use ($requiredSkills, $specificResourceIds, $typeIds, $groupIds) {
                if (! empty($specificResourceIds) && ! in_array($resource->id, $specificResourceIds, true)) {
                    return false;
                }

                if (! empty($typeIds) && ! in_array($resource->resource_type_id, $typeIds, true)) {
                    return false;
                }

                if (! empty($groupIds) && ! in_array($resource->group_id, $groupIds, true)) {
                    return false;
                }

                // PRD 160: Skill compatibility
                if (! empty($requiredSkills) && ! $resource->hasAllSkills($requiredSkills)) {
                    return false;
                }

                return true;
            });
        }

        // If preferred staff is requested
        if ($preferredStaffId !== null) {
            $matched = $staffQuery->firstWhere('id', $preferredStaffId);

            return $matched ? collect([$matched]) : collect();
        }

        return $staffQuery->values();
    }

    /**
     * Convenience method to fetch only available slots.
     *
     * @param  array<string, mixed>  $options
     * @return Collection<int, SlotArray>
     */
    public function getAvailableSlotsForDate(
        Tenant $tenant,
        Service $service,
        CarbonInterface|string $date,
        array $options = []
    ): Collection {
        $options['include_unavailable'] = false;

        return $this->getSlotsForDate($tenant, $service, $date, $options);
    }

    /**
     * Calculate slot availability over a date range.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, Collection<int, SlotArray>>
     */
    public function getSlotsForDateRange(
        Tenant $tenant,
        Service $service,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        array $options = []
    ): array {
        /** @var Business|null $business */
        $business = $tenant->business ?? Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $timezone = $business?->timezone ?: 'Asia/Jakarta';

        $start = is_string($startDate) ? Carbon::parse($startDate, $timezone)->startOfDay() : Carbon::instance($startDate)->setTimezone($timezone)->startOfDay();
        $end = is_string($endDate) ? Carbon::parse($endDate, $timezone)->startOfDay() : Carbon::instance($endDate)->setTimezone($timezone)->startOfDay();

        $results = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->format('Y-m-d');
            $results[$dateKey] = $this->getSlotsForDate($tenant, $service, $cursor, $options);
            $cursor->addDay();
        }

        return $results;
    }

    /**
     * Check single slot availability at a specific timestamp.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     is_available: bool,
     *     reason_code: string|null,
     *     reason_message: string|null,
     *     available_staff_ids: array<int>,
     *     slot: SlotArray|null
     * }
     */
    public function isSlotAvailable(
        Tenant $tenant,
        Service $service,
        CarbonInterface|string $startAt,
        array $options = []
    ): array {
        /** @var Business|null $business */
        $business = $tenant->business ?? Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $timezone = $business?->timezone ?: 'Asia/Jakarta';

        $start = is_string($startAt) ? Carbon::parse($startAt, $timezone) : Carbon::instance($startAt)->setTimezone($timezone);
        $dateString = $start->format('Y-m-d');
        $timeString = $start->format('H:i');

        $slots = $this->getSlotsForDate($tenant, $service, $dateString, array_merge($options, [
            'include_unavailable' => true,
        ]));

        $slot = $slots->firstWhere('start_time', $timeString);

        if (! $slot) {
            return [
                'is_available' => false,
                'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                'available_staff_ids' => [],
                'slot' => null,
            ];
        }

        return [
            'is_available' => $slot['is_available'],
            'reason_code' => $slot['reason_code'],
            'reason_message' => $slot['reason_message'],
            'available_staff_ids' => $slot['available_staff_ids'],
            'slot' => $slot,
        ];
    }
}
