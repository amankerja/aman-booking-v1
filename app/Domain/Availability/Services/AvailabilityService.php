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
 *     is_available: bool,
 *     reason_code: string|null,
 *     reason_message: string|null,
 *     available_staff_ids: array<int>,
 *     available_staff: array<int, array{id: int, name: string}>
 * }
 *
 * Single Source of Truth for availability calculation across AMAN BOOKING.
 *
 * Evaluates interval intersections:
 * (Business Hours ∩ Service Schedule ∩ Staff/Resource Schedule)
 * - (Active Bookings + TimeBlocks/Cuti + Holidays/Blackout + Breaks)
 *
 * Adheres strictly to PRD 19, 20, 137, 197, 198, 204.2, and 218.
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
     *     existing_allocations?: array<int, array{resource_id: int, start_at: CarbonInterface|string, end_at: CarbonInterface|string, status?: string}>,
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

        // 2. Compute effective duration
        $durationResult = $this->durationCalculator->calculate($service, [
            'variant_id' => $options['variant_id'] ?? null,
            'addon_ids' => $options['addon_ids'] ?? [],
            'quantity' => $options['quantity'] ?? 1,
            'custom_duration_minutes' => $options['custom_duration_minutes'] ?? null,
        ]);
        $durationMinutes = $durationResult['total_service_duration'];

        // 3. Resolve step size
        $stepMinutes = (int) ($options['slot_step_minutes'] ?? 30);
        if ($stepMinutes <= 0) {
            $stepMinutes = 30;
        }

        // 4. Resolve business hours & calendar exceptions
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

        // 5. Resolve eligible staff (with skills and preferred staff handling)
        $preferredStaffId = $options['preferred_staff_id'] ?? $options['staff_id'] ?? null;
        $eligibleStaff = $this->getEligibleStaff($tenant, $service, $preferredStaffId ? (int) $preferredStaffId : null);

        // 6. Generate candidate slots
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
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
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

            // Check A: Does slot finish within business operating window?
            if ($slotEnd->gt($businessCloseAt) || $slotStart->lt($businessOpenAt)) {
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            // Check B: Does slot overlap with any business breaks?
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
                    'is_available' => false,
                    'reason_code' => self::REASON_OUTSIDE_BUSINESS_HOURS,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_OUTSIDE_BUSINESS_HOURS],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            // Check C: Staff availability
            $availableStaffForSlot = collect();
            $slotTakenConflict = false;

            if ($eligibleStaff->isEmpty()) {
                // No qualified or preferred staff available
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'is_available' => false,
                    'reason_code' => self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION,
                    'reason_message' => self::REASON_MESSAGES[self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION],
                    'available_staff_ids' => [],
                    'available_staff' => [],
                ]);
                $cursor->addMinutes($stepMinutes);

                continue;
            }

            foreach ($eligibleStaff as $staff) {
                $staffAvailable = $this->isStaffAvailableForWindow(
                    $staff,
                    $slotStart,
                    $slotEnd,
                    $dayOfWeek,
                    $dateString,
                    $timezone,
                    $options,
                    $staffBookingConflict
                );

                if ($staffBookingConflict) {
                    $slotTakenConflict = true;
                }

                if ($staffAvailable) {
                    $availableStaffForSlot->push($staff);
                }
            }

            if ($availableStaffForSlot->isNotEmpty()) {
                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'is_available' => true,
                    'reason_code' => null,
                    'reason_message' => null,
                    'available_staff_ids' => $availableStaffForSlot->pluck('id')->values()->all(),
                    'available_staff' => $availableStaffForSlot->map(fn (Resource $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                    ])->values()->all(),
                ]);
            } else {
                $reasonCode = $slotTakenConflict
                    ? self::REASON_SLOT_TAKEN
                    : self::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION;

                $slots->push([
                    'date' => $dateString,
                    'start_time' => $slotStart->format('H:i'),
                    'end_time' => $slotEnd->format('H:i'),
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'is_available' => false,
                    'reason_code' => $reasonCode,
                    'reason_message' => self::REASON_MESSAGES[$reasonCode],
                    'available_staff_ids' => [],
                    'available_staff' => [],
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
     * Check if a staff resource is available for the given time window.
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
        $bookingConflict = false;

        // 1. Weekly schedule check
        /** @var ResourceSchedule|null $schedule */
        $schedule = ResourceSchedule::withoutGlobalScopes()
            ->where('tenant_id', $staff->tenant_id)
            ->where('resource_id', $staff->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $schedule || ! $schedule->is_available) {
            return false;
        }

        $shiftStart = Carbon::parse("{$dateString} {$schedule->start_time}", $timezone);
        $shiftEnd = Carbon::parse("{$dateString} {$schedule->end_time}", $timezone);

        // PRD 197 check: does slot start before shift or end after shift?
        if ($slotStart->lt($shiftStart) || $slotEnd->gt($shiftEnd)) {
            return false;
        }

        // 2. Staff breaks check
        if (! empty($schedule->breaks)) {
            /** @var array<int, array{start?: string, end?: string, title?: string}> $rawBreaks */
            $rawBreaks = $schedule->breaks;
            foreach ($rawBreaks as $break) {
                $bStartStr = $break['start'] ?? null;
                $bEndStr = $break['end'] ?? null;

                if ($bStartStr && $bEndStr) {
                    $bStart = Carbon::parse("{$dateString} {$bStartStr}", $timezone);
                    $bEnd = Carbon::parse("{$dateString} {$bEndStr}", $timezone);

                    if ($slotStart->lt($bEnd) && $slotEnd->gt($bStart)) {
                        return false;
                    }
                }
            }
        }

        // 3. TimeBlock (leave, cuti, maintenance) check
        $hasTimeBlock = TimeBlock::withoutGlobalScopes()
            ->where('tenant_id', $staff->tenant_id)
            ->affectingResource($staff->id)
            ->overlapping($slotStart, $slotEnd)
            ->exists();

        if ($hasTimeBlock) {
            return false;
        }

        // 4. Existing allocations / bookings conflict check (PRD 19)
        // Check fixture allocations from options
        $fixtureAllocations = $options['existing_allocations'] ?? [];
        if (! empty($fixtureAllocations)) {
            foreach ($fixtureAllocations as $alloc) {
                if ((int) $alloc['resource_id'] === $staff->id) {
                    $allocStart = Carbon::parse($alloc['start_at'], $timezone);
                    $allocEnd = Carbon::parse($alloc['end_at'], $timezone);

                    if ($slotStart->lt($allocEnd) && $slotEnd->gt($allocStart)) {
                        $bookingConflict = true;

                        return false;
                    }
                }
            }
        }

        // Check database allocations if table exists (future-ready for Phase 1.5)
        if (Schema::hasTable('booking_allocations')) {
            $hasDbAllocation = DB::table('booking_allocations')
                ->where('resource_id', $staff->id)
                ->where('start_at', '<', $slotEnd->toDateTimeString())
                ->where('end_at', '>', $slotStart->toDateTimeString())
                ->exists();

            if ($hasDbAllocation) {
                $bookingConflict = true;

                return false;
            }
        }

        return true;
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
            // Collect all required skills
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
                // If rule requires specific resources
                if (! empty($specificResourceIds) && ! in_array($resource->id, $specificResourceIds, true)) {
                    return false;
                }

                // If rule specifies resource types
                if (! empty($typeIds) && ! in_array($resource->resource_type_id, $typeIds, true)) {
                    return false;
                }

                // If rule specifies groups
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
