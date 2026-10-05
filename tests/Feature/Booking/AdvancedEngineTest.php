<?php

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Services\BookingCodeGenerator;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceVariant;
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
    $this->availabilityService = new AvailabilityService($this->calendarService);

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
});

// PRD 130: SEQUENTIAL SERVICE
test('PRD 130: sequential service creates stage-specific allocations with exact windows and frees resources during other stages', function () {
    // 3 different resources for 3 sequential stages
    $assistant = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Assistant Maya',
        'state' => 'AVAILABLE',
    ]);
    $colorist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Colorist Budi',
        'state' => 'AVAILABLE',
    ]);
    $stylist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Stylist Cindy',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Salon Coloring & Styling Sequence',
        'duration_minutes' => 165,
        'price_idr' => 350000,
        'capacity' => 1,
    ]);

    $stages = [
        [
            'name' => 'Wash',
            'duration_minutes' => 15,
            'resource_id' => $assistant->id,
            'role' => 'assistant',
        ],
        [
            'name' => 'Coloring',
            'duration_minutes' => 120,
            'resource_id' => $colorist->id,
            'role' => 'colorist',
        ],
        [
            'name' => 'Styling',
            'duration_minutes' => 30,
            'resource_id' => $stylist->id,
            'role' => 'stylist',
        ],
    ];

    // Customer 1 books the sequential service at 10:00 (10:00 - 12:45)
    $booking1 = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer Satu', 'phone' => '081234567891'],
        'start_at' => '2026-10-15 10:00:00',
        'stages' => $stages,
    ]);

    expect($booking1)->toBeInstanceOf(Booking::class)
        ->and($booking1->start_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($booking1->end_at->toDateTimeString())->toBe('2026-10-15 12:45:00'); // 165 mins later

    // Verify allocations: 3 separate stage allocations
    $allocations = BookingAllocation::where('booking_id', $booking1->id)->orderBy('start_at')->get();
    expect($allocations)->toHaveCount(3);

    // Stage 1: Assistant for 15m (10:00 - 10:15)
    expect($allocations[0]->resource_id)->toBe($assistant->id)
        ->and($allocations[0]->role)->toBe('assistant')
        ->and($allocations[0]->start_at->toDateTimeString())->toBe('2026-10-15 10:00:00')
        ->and($allocations[0]->end_at->toDateTimeString())->toBe('2026-10-15 10:15:00');

    // Stage 2: Colorist for 120m (10:15 - 12:15)
    expect($allocations[1]->resource_id)->toBe($colorist->id)
        ->and($allocations[1]->role)->toBe('colorist')
        ->and($allocations[1]->start_at->toDateTimeString())->toBe('2026-10-15 10:15:00')
        ->and($allocations[1]->end_at->toDateTimeString())->toBe('2026-10-15 12:15:00');

    // Stage 3: Stylist for 30m (12:15 - 12:45)
    expect($allocations[2]->resource_id)->toBe($stylist->id)
        ->and($allocations[2]->role)->toBe('stylist')
        ->and($allocations[2]->start_at->toDateTimeString())->toBe('2026-10-15 12:15:00')
        ->and($allocations[2]->end_at->toDateTimeString())->toBe('2026-10-15 12:45:00');

    // Verify snapshot has stages
    expect($booking1->service_snapshot['stages'])->toHaveCount(3)
        ->and($booking1->service_snapshot['stages'][0]['name'])->toBe('Wash')
        ->and($booking1->service_snapshot['stages'][1]['name'])->toBe('Coloring')
        ->and($booking1->service_snapshot['stages'][2]['name'])->toBe('Styling');

    // Resource Freedom: Assistant Maya is FREE at 10:30 (during Stage 2 Coloring)!
    // Customer 2 can book Assistant Maya for another service at 10:30
    $washService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Quick Wash',
        'duration_minutes' => 30,
        'price_idr' => 50000,
        'capacity' => 1,
    ]);

    $booking2 = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $washService,
        'customer' => ['name' => 'Customer Dua', 'phone' => '081234567892'],
        'start_at' => '2026-10-15 10:30:00',
        'resource_ids' => [$assistant->id],
    ]);

    expect($booking2)->toBeInstanceOf(Booking::class);

    // Conflict Test: If Colorist is already booked at 10:15 - 12:15, a new sequential booking starting at 10:00 will fail
    // because Stage 2 needs Colorist!
    expect(function () use ($service, $stages) {
        $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $service,
            'customer' => ['name' => 'Customer Tiga', 'phone' => '081234567893'],
            'start_at' => '2026-10-15 10:00:00',
            'stages' => $stages,
        ]);
    })->toThrow(BookingException::class);
});

// PRD 131 & 132: MULTI-SERVICE & COMPOSITE PACKAGES
test('PRD 131 & 132: multi-service in single booking accumulates duration and price, snapshots items', function () {
    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Barber John',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Custom Grooming Session',
        'duration_minutes' => 45,
        'price_idr' => 100000,
        'capacity' => 1,
    ]);

    $items = [
        ['service_id' => 101, 'name' => 'Haircut', 'duration_minutes' => 45, 'price_idr' => 100000],
        ['service_id' => 102, 'name' => 'Wash', 'duration_minutes' => 15, 'price_idr' => 30000],
        ['service_id' => 103, 'name' => 'Styling', 'duration_minutes' => 30, 'price_idr' => 70000],
    ];

    $booking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'John Customer', 'phone' => '081234567894'],
        'start_at' => '2026-10-16 14:00:00',
        'resource_ids' => [$staff->id],
        'items' => $items,
    ]);

    // Total duration = 45 + 15 + 30 = 90 minutes. End at 15:30
    expect($booking)->toBeInstanceOf(Booking::class)
        ->and($booking->start_at->toDateTimeString())->toBe('2026-10-16 14:00:00')
        ->and($booking->end_at->toDateTimeString())->toBe('2026-10-16 15:30:00') // 90m
        ->and($booking->total_idr)->toBe(200000); // 100k + 30k + 70k

    // Verify snapshot
    expect($booking->service_snapshot['items'])->toHaveCount(3)
        ->and($booking->service_snapshot['items'][0]['name'])->toBe('Haircut')
        ->and($booking->service_snapshot['items'][1]['name'])->toBe('Wash')
        ->and($booking->service_snapshot['items'][2]['name'])->toBe('Styling');

    // Test AvailabilityService getCompositeSlotsForDate
    $svcHaircut = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 45, 'price_idr' => 100000]);
    $svcWash = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 15, 'price_idr' => 30000]);
    $svcStyling = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 30, 'price_idr' => 70000]);

    $slots = $this->availabilityService->getCompositeSlotsForDate($this->tenant, [$svcHaircut, $svcWash, $svcStyling], '2026-10-16');
    expect($slots->isNotEmpty())->toBeTrue()
        ->and($slots->first()['duration_minutes'])->toBe(90);
});

// PRD 164: COUPLE SPA PACKAGE (MULTI-RESOURCE SIMULTANEOUS)
test('PRD 164: couple spa package requires simultaneous availability of all required resources', function () {
    $therapistA = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist A',
        'state' => 'AVAILABLE',
    ]);
    $therapistB = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist B',
        'state' => 'AVAILABLE',
    ]);
    $coupleRoom = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->spaceType->id,
        'name' => 'Couple Room VIP',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Couple Spa Package',
        'duration_minutes' => 90,
        'price_idr' => 500000,
        'capacity' => 1,
    ]);

    // Configure resource rules for Couple Spa: 2 therapists + 1 room
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->staffType->id,
        'quantity' => 2,
        'is_required' => true,
    ]);
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_id' => $coupleRoom->id,
        'quantity' => 1,
        'is_required' => true,
    ]);

    // When all 3 resources are free, booking succeeds
    $booking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $service,
        'customer' => ['name' => 'Pasangan Bahagia', 'phone' => '081234567895'],
        'start_at' => '2026-10-17 10:00:00',
        'resource_ids' => [$therapistA->id, $therapistB->id, $coupleRoom->id],
    ]);

    expect($booking)->toBeInstanceOf(Booking::class);
    $allocations = BookingAllocation::where('booking_id', $booking->id)->get();
    expect($allocations)->toHaveCount(3);

    // If another couple attempts to book at overlapping time (10:30), it fails because coupleRoom and therapists are busy
    expect(function () use ($service, $therapistA, $therapistB, $coupleRoom) {
        $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $service,
            'customer' => ['name' => 'Pasangan Lain', 'phone' => '081234567896'],
            'start_at' => '2026-10-17 10:30:00',
            'resource_ids' => [$therapistA->id, $therapistB->id, $coupleRoom->id],
        ]);
    })->toThrow(BookingException::class);
});

// PRD 133: SERVICE DEPENDENCY
test('PRD 133: service dependency blocks booking until prerequisite service is completed', function () {
    $consultationService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Initial Consultation',
        'duration_minutes' => 30,
        'price_idr' => 100000,
    ]);

    $mainTattooService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Master Tattoo Session',
        'duration_minutes' => 180,
        'price_idr' => 1500000,
        'rules' => [
            'prerequisite_service_id' => $consultationService->id,
        ],
    ]);

    $artist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Master Artist',
        'state' => 'AVAILABLE',
    ]);

    $customerPhone = '081234567897';

    // Step 1: Customer attempts to book Main Tattoo directly without consultation -> REJECTED
    try {
        $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $mainTattooService,
            'customer' => ['name' => 'Tattoo Lover', 'phone' => $customerPhone],
            'start_at' => '2026-10-18 13:00:00',
            'resource_ids' => [$artist->id],
        ]);
        $this->fail('Expected BookingException for unsatisfied dependency was not thrown');
    } catch (BookingException $e) {
        expect($e->getErrorCode())->toBe('SERVICE_DEPENDENCY_UNSATISFIED')
            ->and($e->getMessage())->toContain('prasyarat');
    }

    // Step 2: Customer books Consultation, but it is not yet COMPLETED (it is CONFIRMED)
    $consultationBooking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $consultationService,
        'customer' => ['name' => 'Tattoo Lover', 'phone' => $customerPhone],
        'start_at' => '2026-10-18 10:00:00',
        'resource_ids' => [$artist->id],
    ]);
    expect($consultationBooking->status_category)->toBe(BookingStatusCategory::CONFIRMED);

    // Attempting to book Main Tattoo still fails because consultation is not COMPLETED
    expect(function () use ($mainTattooService, $customerPhone, $artist) {
        $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $mainTattooService,
            'customer' => ['name' => 'Tattoo Lover', 'phone' => $customerPhone],
            'start_at' => '2026-10-18 13:00:00',
            'resource_ids' => [$artist->id],
        ]);
    })->toThrow(BookingException::class);

    // Step 3: Transition consultation through valid state machine workflow: CONFIRMED -> CHECKED_IN -> IN_PROGRESS -> COMPLETED
    $this->stateMachine->transition($consultationBooking, BookingStatusCategory::CHECKED_IN, [
        'reason' => 'Customer arrived',
        'actor_type' => 'owner',
    ]);
    $this->stateMachine->transition($consultationBooking, BookingStatusCategory::IN_PROGRESS, [
        'reason' => 'Consultation started',
        'actor_type' => 'owner',
    ]);
    $this->stateMachine->transition($consultationBooking, BookingStatusCategory::COMPLETED, [
        'reason' => 'Consultation successfully done',
        'actor_type' => 'owner',
    ]);
    expect($consultationBooking->fresh()->status_category)->toBe(BookingStatusCategory::COMPLETED);

    // Step 4: Now Customer books Main Tattoo -> SUCCEEDS!
    $mainBooking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $mainTattooService,
        'customer' => ['name' => 'Tattoo Lover', 'phone' => $customerPhone],
        'start_at' => '2026-10-18 13:00:00',
        'resource_ids' => [$artist->id],
    ]);

    expect($mainBooking)->toBeInstanceOf(Booking::class)
        ->and($mainBooking->service_id)->toBe($mainTattooService->id);
});

// PRD 135: GROUP BOOKING WITH PARTICIPANTS
test('PRD 135: group booking validates participant list against capacity and records snapshot', function () {
    $coach = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Coach Yoga',
        'state' => 'AVAILABLE',
    ]);

    $yogaClass = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Morning Yoga Class',
        'duration_minutes' => 60,
        'price_idr' => 75000,
        'capacity' => 5, // Max 5 participants
    ]);

    $participants = [
        ['name' => 'Peserta A', 'email' => 'a@example.com'],
        ['name' => 'Peserta B', 'email' => 'b@example.com'],
        ['name' => 'Peserta C', 'email' => 'c@example.com'],
    ];

    // Customer 1 books for 3 participants
    $booking1 = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $yogaClass,
        'customer' => ['name' => 'Group Leader', 'phone' => '081234567898'],
        'start_at' => '2026-10-19 09:00:00',
        'resource_ids' => [$coach->id],
        'participants' => $participants,
    ]);

    expect($booking1)->toBeInstanceOf(Booking::class)
        ->and($booking1->service_snapshot['capacity'])->toBe(5)
        ->and($booking1->service_snapshot['participants'])->toHaveCount(3);

    // Customer 2 tries to book for 3 more participants: 3 + 3 = 6 > 5 -> CAPACITY_FULL
    $nextParticipants = [
        ['name' => 'Peserta D'],
        ['name' => 'Peserta E'],
        ['name' => 'Peserta F'],
    ];

    try {
        $this->createBooking->execute([
            'tenant' => $this->tenant,
            'service' => $yogaClass,
            'customer' => ['name' => 'Another Leader', 'phone' => '081234567899'],
            'start_at' => '2026-10-19 09:00:00',
            'resource_ids' => [$coach->id],
            'participants' => $nextParticipants,
        ]);
        $this->fail('Expected CAPACITY_FULL BookingException was not thrown');
    } catch (BookingException $e) {
        expect($e->getErrorCode())->toBe('CAPACITY_FULL');
    }

    // But Customer 2 can book for 2 participants (3 + 2 = 5 <= 5) -> SUCCEEDS
    $booking2 = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $yogaClass,
        'customer' => ['name' => 'Another Leader', 'phone' => '081234567899'],
        'start_at' => '2026-10-19 09:00:00',
        'resource_ids' => [$coach->id],
        'participants' => array_slice($nextParticipants, 0, 2),
    ]);

    expect($booking2)->toBeInstanceOf(Booking::class);
});

// PRD 123 & 199: VARIABLE, VARIANT & QUANTITY DURATION MODELS
test('PRD 123 & 199: variable, variant, and quantity duration models calculate accurate duration', function () {
    $groomer = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Pet Groomer',
        'state' => 'AVAILABLE',
    ]);

    // 1. Variant Duration (PRD 199: Pet Grooming Small 60m / Large 120m)
    $petService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pet Grooming',
        'duration_minutes' => 60,
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
    ]);
    $variantLarge = ServiceVariant::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $petService->id,
        'name' => 'Large Dog',
        'duration_minutes' => 120,
        'price_idr' => 200000,
    ]);

    $largeBooking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $petService,
        'variant_id' => $variantLarge->id,
        'customer' => ['name' => 'Dog Owner', 'phone' => '081234567810'],
        'start_at' => '2026-10-20 10:00:00',
        'resource_ids' => [$groomer->id],
    ]);

    // Large variant must have 120 minutes duration
    expect($largeBooking->start_at->diffInMinutes($largeBooking->end_at))->toEqual(120);

    // 2. Quantity Duration (PRD 123: Carpet Cleaning 30m * quantity)
    $cleaner = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Carpet Specialist',
        'state' => 'AVAILABLE',
    ]);

    $carpetService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Carpet Cleaning',
        'duration_minutes' => 30,
        'price_idr' => 75000,
        'duration_type' => 'PER_QUANTITY',
        'duration_rule' => ['minutes_per_unit' => 30],
    ]);

    // 4 rooms = 4 * 30 = 120 minutes
    $carpetBooking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $carpetService,
        'quantity' => 4,
        'customer' => ['name' => 'House Owner', 'phone' => '081234567811'],
        'start_at' => '2026-10-20 14:00:00',
        'resource_ids' => [$cleaner->id],
    ]);

    expect($carpetBooking->start_at->diffInMinutes($carpetBooking->end_at))->toEqual(120);

    // 3. Variable Duration (PRD 123: Tattoo 60-240 min, custom 150 min)
    $tattooService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Custom Tattoo',
        'duration_minutes' => 60,
        'price_idr' => 500000,
        'duration_type' => 'VARIABLE',
        'duration_rule' => ['min_minutes' => 60, 'max_minutes' => 240],
    ]);

    $customBooking = $this->createBooking->execute([
        'tenant' => $this->tenant,
        'service' => $tattooService,
        'custom_duration_minutes' => 150,
        'customer' => ['name' => 'Tattoo Enthusiast', 'phone' => '081234567812'],
        'start_at' => '2026-10-21 10:00:00',
        'resource_ids' => [$cleaner->id],
    ]);

    expect($customBooking->start_at->diffInMinutes($customBooking->end_at))->toEqual(150);
});

// PERFORMANCE BENCHMARK
test('performance benchmark: availability engine latency for single day is under 100ms', function () {
    $service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Benchmark Service',
        'duration_minutes' => 60,
        'price_idr' => 100000,
    ]);

    $resources = Resource::factory()->count(4)->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    foreach ($resources as $res) {
        for ($d = 0; $d <= 6; $d++) {
            ResourceSchedule::create([
                'tenant_id' => $this->tenant->id,
                'resource_id' => $res->id,
                'day_of_week' => $d,
                'is_available' => true,
                'start_time' => '08:00:00',
                'end_time' => '20:00:00',
            ]);
        }
    }

    // Warm-up query
    $this->availabilityService->getSlotsForDate($this->tenant, $service, '2026-10-22');

    $durations = [];
    $iterations = 10;

    for ($i = 0; $i < $iterations; $i++) {
        $t0 = microtime(true);
        $this->availabilityService->getSlotsForDate($this->tenant, $service, '2026-10-22');
        $durations[] = (microtime(true) - $t0) * 1000; // ms
    }

    sort($durations);
    $p95Index = (int) ceil(0.95 * $iterations) - 1;
    $p95 = $durations[$p95Index];

    // Assert p95 per single day availability calculation is under 100ms
    expect($p95)->toBeLessThan(100.0);
});
