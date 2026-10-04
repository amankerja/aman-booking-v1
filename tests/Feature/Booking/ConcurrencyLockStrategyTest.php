<?php

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Services\BookingCodeGenerator;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->calendarService = new BusinessCalendarService;
    $this->codeGenerator = new BookingCodeGenerator;
    $this->stateMachine = new BookingStateMachine;
    $this->createBooking = new CreateBooking($this->codeGenerator, $this->calendarService);
    $this->bookingService = new BookingService($this->createBooking, $this->stateMachine);

    $this->tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'timezone' => 'Asia/Jakarta',
        'settings' => [
            'min_advance_hours' => 0,
            'max_advance_days' => 90,
        ],
    ]);

    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '22:00:00',
            'breaks' => [],
        ]);
    }

    $this->staffType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);
    $this->spaceType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'room',
        'name' => 'Room',
        'is_space' => true,
    ]);

    $this->staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Budi Staff',
        'state' => 'AVAILABLE',
    ]);
    $this->room = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->spaceType->id,
        'name' => 'Room VIP 1',
        'state' => 'AVAILABLE',
    ]);
});

test('multi-resource locking sorts resource IDs deterministically preventing deadlocks', function () {
    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Multi-Resource Treatment',
        'duration_minutes' => 60,
        'price_idr' => 300000,
        'capacity' => 1,
    ]);

    $slotTime = Carbon::parse('2026-11-10 10:00:00', 'Asia/Jakarta')->setTimezone('UTC');

    // Create with unsorted resource IDs [room, staff] (e.g. higher ID first or lower ID first)
    $lowId = min($this->staff->id, $this->room->id);
    $highId = max($this->staff->id, $this->room->id);

    $booking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer A', 'phone' => '081200000001'],
        'start_at' => $slotTime->toIso8601String(),
        'resource_ids' => [$highId, $lowId], // purposely pass reverse order
    ]);

    expect($booking->status_category->value)->toBe('CONFIRMED');

    // Allocations created for both resources
    $allocs = BookingAllocation::where('booking_id', $booking->id)->get();
    expect($allocs->count())->toBe(2);
    expect($allocs->pluck('resource_id')->sort()->values()->all())->toBe([$lowId, $highId]);
});

test('identical slot collision is immediately rejected with SLOT_TAKEN exception', function () {
    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Single Slot Facial',
        'duration_minutes' => 45,
        'price_idr' => 150000,
        'capacity' => 1,
    ]);

    $slotTime = Carbon::parse('2026-11-10 14:00:00', 'Asia/Jakarta')->setTimezone('UTC');

    // First booking takes the slot
    $booking1 = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer 1', 'phone' => '081200000001'],
        'start_at' => $slotTime->toIso8601String(),
        'staff_id' => $this->staff->id,
    ]);
    expect($booking1->status_category->value)->toBe('CONFIRMED');

    // Second booking targeting exact same slot is rejected
    expect(fn () => $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer 2', 'phone' => '081200000002'],
        'start_at' => $slotTime->toIso8601String(),
        'staff_id' => $this->staff->id,
    ]))->toThrow(BookingException::class, 'baru saja dipesan customer lain');
});

test('class capacity strictly enforces maximum limit and rejects excess with CAPACITY_FULL', function () {
    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Yoga Class 5 Persons',
        'duration_minutes' => 60,
        'price_idr' => 75000,
        'capacity' => 5, // Capacity of 5
    ]);

    $slotTime = Carbon::parse('2026-11-10 16:00:00', 'Asia/Jakarta')->setTimezone('UTC');

    // 5 bookings fill the capacity
    for ($i = 1; $i <= 5; $i++) {
        $booking = $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $service,
            'customer' => ['name' => "Participant {$i}", 'phone' => "08120000000{$i}"],
            'start_at' => $slotTime->toIso8601String(),
            'resource_ids' => [$this->room->id],
            'quantity' => 1,
        ]);
        expect($booking->status_category->value)->toBe('CONFIRMED');
    }

    // 6th booking fails with CAPACITY_FULL
    expect(fn () => $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Participant 6', 'phone' => '081200000006'],
        'start_at' => $slotTime->toIso8601String(),
        'resource_ids' => [$this->room->id],
        'quantity' => 1,
    ]))->toThrow(BookingException::class, 'Kuota untuk jadwal ini sudah penuh');

    // Verify sum of quantities in allocations is exactly 5
    $totalBooked = BookingAllocation::where('resource_id', $this->room->id)
        ->where('status', 'ACTIVE')
        ->sum('quantity');
    expect($totalBooked)->toBe(5);
});

test('multiple distinct time slots on the same resource succeed without conflicts', function () {
    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Haircut 30m',
        'duration_minutes' => 30,
        'price_idr' => 50000,
        'capacity' => 1,
    ]);

    $baseTime = Carbon::parse('2026-11-10 09:00:00', 'Asia/Jakarta')->setTimezone('UTC');

    // 6 consecutive 30-minute slots on the same staff
    for ($i = 0; $i < 6; $i++) {
        $slot = $baseTime->copy()->addMinutes($i * 30);
        $booking = $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $service,
            'customer' => ['name' => "Customer Slot {$i}", 'phone' => "08121111222{$i}"],
            'start_at' => $slot->toIso8601String(),
            'staff_id' => $this->staff->id,
        ]);
        expect($booking->status_category->value)->toBe('CONFIRMED');
    }

    $allocCount = BookingAllocation::where('resource_id', $this->staff->id)
        ->where('status', 'ACTIVE')
        ->count();
    expect($allocCount)->toBe(6);
});
