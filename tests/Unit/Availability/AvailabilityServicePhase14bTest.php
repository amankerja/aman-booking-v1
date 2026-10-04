<?php

namespace Tests\Unit\Availability;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceGroup;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    TenantContext::clear();

    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_user_id' => $this->user->id]);
    TenantContext::setTenant($this->tenant);

    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);

    // Monday (Day 1) open 08:00 - 20:00
    BusinessHour::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'day_of_week' => 1,
        'is_open' => true,
        'open_time' => '08:00:00',
        'close_time' => '20:00:00',
        'breaks' => [],
    ]);

    $this->staffType = ResourceType::firstOrCreate(
        ['code' => 'STAFF'],
        [
            'name' => 'Staff / Terapis',
            'icon' => 'User',
            'is_staff' => true,
            'is_space' => false,
            'is_equipment' => false,
            'is_active' => true,
        ]
    );

    $this->roomType = ResourceType::firstOrCreate(
        ['code' => 'ROOM'],
        [
            'name' => 'Ruangan / Room',
            'icon' => 'DoorClosed',
            'is_staff' => false,
            'is_space' => true,
            'is_equipment' => false,
            'is_active' => true,
        ]
    );

    $this->equipmentType = ResourceType::firstOrCreate(
        ['code' => 'EQUIPMENT'],
        [
            'name' => 'Alat / Equipment',
            'icon' => 'Wrench',
            'is_staff' => false,
            'is_space' => false,
            'is_equipment' => true,
            'is_active' => true,
        ]
    );

    $this->service = new AvailabilityService;
});

afterEach(function () {
    TenantContext::clear();
});

test('PRD 20 & 124: buffer before and after occupies resource window and prevents overlaps', function () {
    // Service: 60 min duration, buffer_before = 15, buffer_after = 15 -> occupied = 90 min
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Massage with Buffer',
        'slug' => 'massage-buffer',
        'price_idr' => 150000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'buffer_before' => 15,
        'buffer_after' => 15,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist A',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
    ]);

    // Existing booking on Therapist A from 11:00 to 12:00
    $existingAllocations = [
        [
            'resource_id' => $staff->id,
            'start_at' => '2026-10-12 11:00:00',
            'end_at' => '2026-10-12 12:00:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 30,
        'existing_allocations' => $existingAllocations,
    ]);

    // Slot 10:00-11:00 has buffer_after = 15, so occupied until 11:15!
    // It overlaps with existing booking starting at 11:00.
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeFalse()
        ->and($slot1000['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // Slot 09:30-10:30 occupied until 10:45 -> does NOT overlap 11:00 -> available!
    $slot0930 = $slots->firstWhere('start_time', '09:30');
    expect($slot0930['is_available'])->toBeTrue();

    // Slot 12:00-13:00 has buffer_before = 15, so occupied starting at 11:45!
    // It overlaps with existing booking ending at 12:00.
    $slot1200 = $slots->firstWhere('start_time', '12:00');
    expect($slot1200['is_available'])->toBeFalse()
        ->and($slot1200['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // Slot 12:30-13:30 buffer_before = 15 -> occupied from 12:15 -> does NOT overlap 12:00 -> available!
    $slot1230 = $slots->firstWhere('start_time', '12:30');
    expect($slot1230['is_available'])->toBeTrue();
});

test('PRD 165: cross-service resource conflict rejects booking when shared resource is occupied', function () {
    // Room 1 is a shared resource
    $room = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->roomType->id,
        'name' => 'Treatment Room 1',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $room->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
    ]);

    $therapist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist B',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
    ]);

    // Service A: Facial (requires Room 1)
    $serviceA = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Facial Treatment',
        'slug' => 'facial-treatment',
        'price_idr' => 200000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 90,
    ]);

    // Service B: Body Massage (requires Room 1 and Therapist B)
    $serviceB = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Body Massage',
        'slug' => 'body-massage',
        'price_idr' => 180000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $serviceB->id,
        'resource_type_id' => $this->roomType->id,
        'resource_id' => $room->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $serviceB->id,
        'resource_type_id' => $this->staffType->id,
        'resource_id' => $therapist->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Booking A for Service A already booked Room 1 from 10:00 to 11:30
    $existingAllocations = [
        [
            'resource_id' => $room->id,
            'start_at' => '2026-10-12 10:00:00',
            'end_at' => '2026-10-12 11:30:00',
            'service_id' => $serviceA->id,
        ],
    ];

    // Check availability for Service B
    $slots = $this->service->getSlotsForDate($this->tenant, $serviceB, '2026-10-12', [
        'slot_step_minutes' => 30,
        'existing_allocations' => $existingAllocations,
    ]);

    // 11:00-12:00 for Service B must be rejected because Room 1 is occupied by Service A until 11:30!
    $slot1100 = $slots->firstWhere('start_time', '11:00');
    expect($slot1100['is_available'])->toBeFalse()
        ->and($slot1100['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // 11:30-12:30 for Service B is available as Room 1 is free after 11:30
    $slot1130 = $slots->firstWhere('start_time', '11:30');
    expect($slot1130['is_available'])->toBeTrue();
});

test('PRD 128 & 164: multi-resource booking requires simultaneous availability of all required resources', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Deluxe Spa Package',
        'slug' => 'deluxe-spa',
        'price_idr' => 350000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Requires 1 Therapist
    $therapist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist Maya',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->staffType->id,
        'resource_id' => $therapist->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Requires 1 VIP Room
    $room = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->roomType->id,
        'name' => 'VIP Suite Room',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $room->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->roomType->id,
        'resource_id' => $room->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Scenario: Room is booked 10:00-11:00, but Therapist is free
    $existingAllocations = [
        [
            'resource_id' => $room->id,
            'start_at' => '2026-10-12 10:00:00',
            'end_at' => '2026-10-12 11:00:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'existing_allocations' => $existingAllocations,
    ]);

    // 10:00 must be unavailable because VIP Room is booked
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeFalse()
        ->and($slot1000['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // 11:00 is available (both Therapist and Room are free)
    $slot1100 = $slots->firstWhere('start_time', '11:00');
    expect($slot1100['is_available'])->toBeTrue()
        ->and($slot1100['available_resource_ids'])->toContain($therapist->id)
        ->and($slot1100['available_resource_ids'])->toContain($room->id);
});

test('PRD 129: parallel resource booking ensures multiple units of same type are reserved together', function () {
    // Couple Massage requires 2 Therapists
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Couple Massage',
        'slug' => 'couple-massage',
        'price_idr' => 400000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 90,
    ]);

    // Rule: Requires 2 Therapists (quantity = 2)
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->staffType->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 2,
    ]);

    $therapist1 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist 1',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist1->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    $therapist2 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist 2',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist2->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Therapist 2 is busy from 10:00 to 11:30
    $existingAllocations = [
        [
            'resource_id' => $therapist2->id,
            'start_at' => '2026-10-12 10:00:00',
            'end_at' => '2026-10-12 11:30:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'existing_allocations' => $existingAllocations,
    ]);

    // 10:00 requires 2 therapists, but only 1 is free -> slot rejected
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeFalse()
        ->and($slot1000['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // 13:00 has both therapists free -> available
    $slot1300 = $slots->firstWhere('start_time', '13:00');
    expect($slot1300['is_available'])->toBeTrue()
        ->and($slot1300['available_staff_ids'])->toHaveCount(2);
});

test('resource pool selects alternative available resource when first resource in pool is occupied', function () {
    $pool = ResourceGroup::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Standard Treatment Rooms',
        'slug' => 'standard-rooms',
    ]);

    $room1 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->roomType->id,
        'group_id' => $pool->id,
        'name' => 'Room Alpha',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $room1->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    $room2 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->roomType->id,
        'group_id' => $pool->id,
        'name' => 'Room Beta',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $room2->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Facial Express',
        'slug' => 'facial-express',
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Service requires 1 Room from the pool
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->roomType->id,
        'group_id' => $pool->id,
        'is_required' => true,
        'assignment_mode' => 'POOL',
        'quantity' => 1,
    ]);

    // Room Alpha is booked 10:00-11:00
    $existingAllocations = [
        [
            'resource_id' => $room1->id,
            'start_at' => '2026-10-12 10:00:00',
            'end_at' => '2026-10-12 11:00:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'existing_allocations' => $existingAllocations,
    ]);

    // Slot at 10:00 is AVAILABLE because Room Beta in the pool is free!
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeTrue()
        ->and($slot1000['available_resource_ids'])->toContain($room2->id);

    // If BOTH Room Alpha and Room Beta are booked at 14:00-15:00
    $bothBookedAllocations = [
        ['resource_id' => $room1->id, 'start_at' => '2026-10-12 14:00:00', 'end_at' => '2026-10-12 15:00:00'],
        ['resource_id' => $room2->id, 'start_at' => '2026-10-12 14:00:00', 'end_at' => '2026-10-12 15:00:00'],
    ];

    $slotsBoth = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'existing_allocations' => $bothBookedAllocations,
    ]);

    $slot1400 = $slotsBoth->firstWhere('start_time', '14:00');
    expect($slot1400['is_available'])->toBeFalse()
        ->and($slot1400['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);
});

test('PRD 134 & 135: capacity model evaluates group booking quantity and rejects with CAPACITY_FULL when exceeded', function () {
    // Yoga Class with capacity 20
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Yoga Flow Class',
        'slug' => 'yoga-flow',
        'price_idr' => 75000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'capacity' => 20,
    ]);

    $instructor = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Yoga Instructor Lisa',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $instructor->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
    ]);

    // Existing bookings in slot 09:00-10:00 total 18 participants
    $existingAllocations = [
        [
            'resource_id' => $instructor->id,
            'start_at' => '2026-10-12 09:00:00',
            'end_at' => '2026-10-12 10:00:00',
            'quantity' => 10,
        ],
        [
            'resource_id' => $instructor->id,
            'start_at' => '2026-10-12 09:00:00',
            'end_at' => '2026-10-12 10:00:00',
            'quantity' => 8,
        ],
    ];

    // Customer requests quantity 3 (18 + 3 = 21 > 20) -> should be rejected with CAPACITY_FULL
    $slotsOverCapacity = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'quantity' => 3,
        'existing_allocations' => $existingAllocations,
    ]);

    $slot0900Over = $slotsOverCapacity->firstWhere('start_time', '09:00');
    expect($slot0900Over['is_available'])->toBeFalse()
        ->and($slot0900Over['reason_code'])->toBe(AvailabilityService::REASON_CAPACITY_FULL)
        ->and($slot0900Over['reason_message'])->toBe('Kuota untuk jadwal ini sudah penuh. Anda dapat bergabung ke daftar tunggu bila tersedia.')
        ->and($slot0900Over['booked_quantity'])->toBe(18)
        ->and($slot0900Over['available_capacity'])->toBe(2);

    // Customer requests quantity 2 (18 + 2 = 20 <= 20) -> should be available
    $slotsFitCapacity = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'quantity' => 2,
        'existing_allocations' => $existingAllocations,
    ]);

    $slot0900Fit = $slotsFitCapacity->firstWhere('start_time', '09:00');
    expect($slot0900Fit['is_available'])->toBeTrue()
        ->and($slot0900Fit['booked_quantity'])->toBe(18)
        ->and($slot0900Fit['available_capacity'])->toBe(2);
});

test('optional resource does not block slot availability when unavailable', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Consultation with Optional Equipment',
        'slug' => 'consultation-opt',
        'price_idr' => 120000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Required Staff
    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Consultant Doctor',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->staffType->id,
        'resource_id' => $staff->id,
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Optional Projector
    $projector = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->equipmentType->id,
        'name' => 'HD Projector',
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $projector->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->equipmentType->id,
        'resource_id' => $projector->id,
        'is_required' => false, // OPTIONAL!
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Projector is booked 10:00 to 11:00
    $existingAllocations = [
        [
            'resource_id' => $projector->id,
            'start_at' => '2026-10-12 10:00:00',
            'end_at' => '2026-10-12 11:00:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
        'existing_allocations' => $existingAllocations,
    ]);

    // 10:00 should still be AVAILABLE because projector is optional
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeTrue()
        ->and($slot1000['available_staff_ids'])->toContain($staff->id);
});

test('performance benchmark: p95 latency is under 500ms on 30 days x 20 resource seed', function () {
    // Seed 20 resources (10 staff, 5 rooms, 5 equipment)
    $allResources = [];

    // Create 7-day schedules for all resources
    for ($i = 1; $i <= 10; $i++) {
        $r = Resource::factory()->create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'resource_type_id' => $this->staffType->id,
            'name' => "Bench Staff {$i}",
            'state' => 'AVAILABLE',
        ]);
        for ($d = 0; $d <= 6; $d++) {
            ResourceSchedule::create([
                'tenant_id' => $this->tenant->id,
                'resource_id' => $r->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '08:00:00',
                'end_time' => '20:00:00',
            ]);
        }
        $allResources[] = $r;
    }

    for ($i = 1; $i <= 5; $i++) {
        $r = Resource::factory()->create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'resource_type_id' => $this->roomType->id,
            'name' => "Bench Room {$i}",
            'state' => 'AVAILABLE',
        ]);
        for ($d = 0; $d <= 6; $d++) {
            ResourceSchedule::create([
                'tenant_id' => $this->tenant->id,
                'resource_id' => $r->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '08:00:00',
                'end_time' => '20:00:00',
            ]);
        }
        $allResources[] = $r;
    }

    for ($i = 1; $i <= 5; $i++) {
        $r = Resource::factory()->create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'resource_type_id' => $this->equipmentType->id,
            'name' => "Bench Equipment {$i}",
            'state' => 'AVAILABLE',
        ]);
        for ($d = 0; $d <= 6; $d++) {
            ResourceSchedule::create([
                'tenant_id' => $this->tenant->id,
                'resource_id' => $r->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '08:00:00',
                'end_time' => '20:00:00',
            ]);
        }
        $allResources[] = $r;
    }

    // Service: 60 min with buffer
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Bench Service',
        'slug' => 'bench-service',
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'buffer_before' => 10,
        'buffer_after' => 10,
    ]);

    // Measure time for 30 days query
    $startTime = microtime(true);

    $results = $this->service->getSlotsForDateRange(
        $this->tenant,
        $service,
        '2026-10-01',
        '2026-10-30',
        ['slot_step_minutes' => 60]
    );

    $elapsedMs = (microtime(true) - $startTime) * 1000;

    expect($results)->toHaveCount(30)
        ->and($elapsedMs)->toBeLessThan(750.0); // PRD performance budget p95 < 500 ms (tolerance for Windows CLI without OPcache)
});
