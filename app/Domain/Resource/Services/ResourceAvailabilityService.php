<?php

namespace App\Domain\Resource\Services;

use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class ResourceAvailabilityService
{
    /**
     * Determine if a resource is available for a given time window.
     */
    public function isResourceAvailable(Resource $resource, CarbonInterface $startAt, CarbonInterface $endAt, ?string $timezone = null): bool
    {
        // 1. Must not be archived
        if ($resource->is_archived) {
            return false;
        }

        // 2. Must be in AVAILABLE state
        if ($resource->state !== 'AVAILABLE') {
            return false;
        }

        // 3. Resolve timezone (fallback to resource's business timezone or Asia/Jakarta)
        $business = $resource->business;
        $tz = $timezone ?? ($business ? $business->timezone : 'Asia/Jakarta');
        $localStart = Carbon::instance($startAt)->setTimezone($tz);
        $localEnd = Carbon::instance($endAt)->setTimezone($tz);

        // Cannot span multiple calendar days for a standard slot booking
        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            return false;
        }

        // 4. Check weekly schedule (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
        $dayOfWeek = $localStart->dayOfWeek;

        /** @var ResourceSchedule|null $schedule */
        $schedule = ResourceSchedule::withoutGlobalScopes()
            ->where('tenant_id', $resource->tenant_id)
            ->where('resource_id', $resource->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        // If no schedule configured or marked as unavailable/day off
        if (! $schedule || ! $schedule->is_available) {
            return false;
        }

        // Validate within schedule operating hours
        $scheduleStart = Carbon::parse($localStart->toDateString().' '.$schedule->start_time, $tz);
        $scheduleEnd = Carbon::parse($localStart->toDateString().' '.$schedule->end_time, $tz);

        if ($localStart->lt($scheduleStart) || $localEnd->gt($scheduleEnd)) {
            return false;
        }

        // 5. Validate break intervals
        if (! empty($schedule->breaks)) {
            foreach ($schedule->breaks as $break) {
                if (empty($break['start']) || empty($break['end'])) {
                    continue;
                }

                $breakStart = Carbon::parse($localStart->toDateString().' '.$break['start'], $tz);
                $breakEnd = Carbon::parse($localStart->toDateString().' '.$break['end'], $tz);

                // Overlap condition: startAt < breakEnd AND endAt > breakStart
                if ($localStart->lt($breakEnd) && $localEnd->gt($breakStart)) {
                    return false;
                }
            }
        }

        // 6. Check manual time blocks (cuti, maintenance, manual blocking)
        $hasTimeBlock = TimeBlock::withoutGlobalScopes()
            ->where('tenant_id', $resource->tenant_id)
            ->affectingResource($resource->id)
            ->overlapping($startAt, $endAt)
            ->exists();

        if ($hasTimeBlock) {
            return false;
        }

        return true;
    }

    /**
     * Check if a resource has all required skills (PRD 160).
     *
     * @param  array<string>  $requiredSkills
     */
    public function checkSkillCompatibility(Resource $resource, array $requiredSkills): bool
    {
        if (empty($requiredSkills)) {
            return true;
        }

        return $resource->hasAllSkills($requiredSkills);
    }

    /**
     * Find all available resources for a specific tenant and window, optionally filtered by type and skills.
     *
     * @param  array<string>  $requiredSkills
     * @return \Illuminate\Support\Collection<int, resource>
     */
    public function getAvailableResources(
        Tenant $tenant,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ?int $resourceTypeId = null,
        array $requiredSkills = [],
        ?string $timezone = null
    ): \Illuminate\Support\Collection {
        $query = Resource::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->available()
            ->with(['resourceType', 'schedules', 'group']);

        if ($resourceTypeId) {
            $query->where('resource_type_id', $resourceTypeId);
        }

        /** @var Collection<int, resource> $candidates */
        $candidates = $query->get();

        return $candidates->filter(function (Resource $resource) use ($startAt, $endAt, $requiredSkills, $timezone) {
            if (! $this->checkSkillCompatibility($resource, $requiredSkills)) {
                return false;
            }

            return $this->isResourceAvailable($resource, $startAt, $endAt, $timezone);
        })->values();
    }
}
