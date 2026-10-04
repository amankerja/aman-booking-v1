<?php

namespace Database\Factories;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Resource\Models\Resource;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAllocation>
 */
class BookingAllocationFactory extends Factory
{
    protected $model = BookingAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = now()->addDay()->setTime(10, 0);
        $endAt = $startAt->copy()->addMinutes(60);

        return [
            'tenant_id' => Tenant::factory(),
            'booking_id' => Booking::factory(),
            'resource_id' => Resource::factory(),
            'role' => 'staff',
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => AllocationStatus::ACTIVE,
            'quantity' => 1,
        ];
    }
}
