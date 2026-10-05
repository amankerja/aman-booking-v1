<?php

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Models\BookingCounter;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Booking\Services\BookingCodeGenerator;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->calendarService = new BusinessCalendarService;
    $this->codeGenerator = new BookingCodeGenerator;
    $this->stateMachine = new BookingStateMachine;
    $this->createBookingAction = new CreateBooking($this->codeGenerator, $this->calendarService);
    $this->bookingService = new BookingService($this->createBookingAction, $this->stateMachine);

    // Setup base tenant, business with open hours
    $this->tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'timezone' => 'Asia/Jakarta',
        'settings' => [
            'min_advance_hours' => 0,
            'max_advance_days' => 60,
        ],
    ]);

    // Open Monday through Sunday 08:00 - 20:00
    for ($day = 0; $day <= 6; $day++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
            'breaks' => [],
        ]);
    }

    $this->staffType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);

    $this->staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist Budi',
        'state' => 'AVAILABLE',
    ]);

    $this->service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Traditional Massage',
        'price_idr' => 200000,
        'duration_minutes' => 60,
        'buffer_before' => 10,
        'buffer_after' => 10,
        'capacity' => 1,
        'archived_at' => null,
    ]);
});

test('it generates sequential booking codes per tenant and date with row lock', function () {
    $date = Carbon::parse('2026-10-05');

    $code1 = $this->codeGenerator->generate($this->tenant->id, $date);
    $code2 = $this->codeGenerator->generate($this->tenant->id, $date);
    $code3 = $this->codeGenerator->generate($this->tenant->id, $date);

    expect($code1)->toBe('BK-20261005-00001');
    expect($code2)->toBe('BK-20261005-00002');
    expect($code3)->toBe('BK-20261005-00003');

    $counter = BookingCounter::where('tenant_id', $this->tenant->id)->where('date', '2026-10-05')->first();
    expect($counter)->not->toBeNull();
    expect($counter->last_number)->toBe(3);
});

test('it creates booking successfully with service snapshot, customer, allocations, and audit log', function () {
    $bookingTime = Carbon::now('Asia/Jakarta')->addDays(2)->setTime(10, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => [
            'name' => 'Ahmad Customer',
            'phone' => '081234567890',
            'email' => 'ahmad@example.com',
        ],
        'start_at' => $bookingTime,
        'staff_id' => $this->staff->id,
        'source' => 'PUBLIC',
    ]);

    expect($booking)->toBeInstanceOf(Booking::class);
    expect($booking->code)->toStartWith('BK-');
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED);
    expect($booking->total_idr)->toBe(200000);

    // Customer E.164 normalization
    expect($booking->customer->phone_e164)->toBe('+6281234567890');
    expect($booking->customer->name)->toBe('Ahmad Customer');

    // Snapshot check
    expect($booking->service_snapshot['name'])->toBe('Traditional Massage');
    expect($booking->service_snapshot['buffer_before'])->toBe(10);
    expect($booking->service_snapshot['buffer_after'])->toBe(10);

    // Allocations check (start_at and end_at must include buffer)
    expect($booking->allocations)->toHaveCount(1);
    /** @var BookingAllocation $allocation */
    $allocation = $booking->allocations->first();
    expect($allocation->resource_id)->toBe($this->staff->id);
    expect($allocation->status)->toBe(AllocationStatus::ACTIVE);

    // Buffer check: occupied start is 10 min before, occupied end is 10 min after
    expect($allocation->start_at->toDateTimeString())->toBe($bookingTime->copy()->subMinutes(10)->toDateTimeString());
    expect($allocation->end_at->toDateTimeString())->toBe($bookingTime->copy()->addMinutes(70)->toDateTimeString());

    // Status history check
    $history = BookingStatusHistory::where('booking_id', $booking->id)->first();
    expect($history)->not->toBeNull();
    expect($history->to_category)->toBe('CONFIRMED');

    // Audit log check
    $this->assertDatabaseHas('audit_logs', [
        'tenant_id' => $this->tenant->id,
        'action' => 'booking.create',
        'entity_type' => 'booking',
        'entity_id' => $booking->id,
    ]);
});

test('it rejects overlapping booking on same resource with SLOT_TAKEN 409', function () {
    $bookingTime = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(14, 0)->setTimezone('UTC');

    // 1st booking succeeds
    $booking1 = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Cust 1', 'phone' => '081111111111'],
        'start_at' => $bookingTime,
        'staff_id' => $this->staff->id,
    ]);
    expect($booking1->id)->toBeGreaterThan(0);

    // 2nd booking at the exact same time
    expect(function () use ($bookingTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Cust 2', 'phone' => '082222222222'],
            'start_at' => $bookingTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('SLOT_TAKEN');
        expect($e->getStatusCode())->toBe(409);
        expect($e->getMessageUser())->toContain('Slot tersebut baru saja dipesan customer lain');
    });

    // 3rd booking during the buffer window (e.g. 5 minutes before end of service, overlapping buffer_after)
    expect(function () use ($bookingTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Cust 3', 'phone' => '083333333333'],
            'start_at' => $bookingTime->copy()->addMinutes(65), // Service ends at +60, buffer ends at +70
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('SLOT_TAKEN');
    });
});

test('it rejects booking when group capacity is exceeded with CAPACITY_FULL 409', function () {
    $groupService = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Yoga Class',
        'price_idr' => 75000,
        'duration_minutes' => 60,
        'buffer_before' => 0,
        'buffer_after' => 0,
        'capacity' => 5, // Max 5 participants
        'archived_at' => null,
    ]);

    $slotTime = Carbon::now('Asia/Jakarta')->addDays(4)->setTime(9, 0)->setTimezone('UTC');

    // 1st booking: 3 spots -> succeeds (remaining: 2)
    $b1 = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $groupService,
        'customer' => ['name' => 'Group 1', 'phone' => '081230000001'],
        'start_at' => $slotTime,
        'quantity' => 3,
        'staff_id' => $this->staff->id,
    ]);
    expect($b1)->not->toBeNull();

    // 2nd booking: 2 spots -> succeeds (remaining: 0)
    $b2 = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $groupService,
        'customer' => ['name' => 'Group 2', 'phone' => '081230000002'],
        'start_at' => $slotTime,
        'quantity' => 2,
        'staff_id' => $this->staff->id,
    ]);
    expect($b2)->not->toBeNull();

    // 3rd booking: 1 spot -> throws CAPACITY_FULL
    expect(function () use ($groupService, $slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $groupService,
            'customer' => ['name' => 'Group 3', 'phone' => '081230000003'],
            'start_at' => $slotTime,
            'quantity' => 1,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('CAPACITY_FULL');
        expect($e->getStatusCode())->toBe(409);
        expect($e->getMessageUser())->toContain('Kuota untuk jadwal ini sudah penuh');
    });
});

test('it returns existing booking on repeated request with same Idempotency-Key without double-booking', function () {
    $idempotencyKey = 'idemp_test_key_'.Str::uuid();
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(5)->setTime(11, 0)->setTimezone('UTC');

    // 1st execution
    $b1 = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Budi Idemp', 'phone' => '081299998888'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
        'idempotency_key' => $idempotencyKey,
    ]);

    // 2nd execution with same key
    $b2 = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Budi Idemp', 'phone' => '081299998888'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
        'idempotency_key' => $idempotencyKey,
    ]);

    expect($b1->id)->toBe($b2->id);
    expect($b1->code)->toBe($b2->code);

    // Verify exactly 1 booking exists in DB
    expect(Booking::where('tenant_id', $this->tenant->id)->count())->toBe(1);
    expect(BookingAllocation::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('it handles all valid state transitions in BookingStateMachine with correct side effects', function () {
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(6)->setTime(15, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Transition Test', 'phone' => '081277776666'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
        'requires_payment' => true, // Starts as PENDING
    ]);

    expect($booking->status_category)->toBe(BookingStatusCategory::PENDING);
    expect($booking->allocations->first()->status)->toBe(AllocationStatus::ACTIVE);

    // Customer pays or payment is verified (PRD 24 payment guard)
    $booking->update(['payment_status' => 'PAID']);

    // PENDING -> CONFIRMED
    $this->bookingService->transition($booking, BookingStatusCategory::CONFIRMED);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CONFIRMED);

    // CONFIRMED -> CHECKED_IN
    $this->bookingService->transition($booking, BookingStatusCategory::CHECKED_IN);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CHECKED_IN);

    // CHECKED_IN -> IN_PROGRESS
    $this->bookingService->transition($booking, BookingStatusCategory::IN_PROGRESS);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::IN_PROGRESS);

    // IN_PROGRESS -> COMPLETED (allocations become CONSUMED)
    $this->bookingService->transition($booking, BookingStatusCategory::COMPLETED);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::COMPLETED);
    expect($booking->fresh()->allocations->first()->status)->toBe(AllocationStatus::CONSUMED);
});

test('it releases allocations and tracks no_show count on NO_SHOW transition', function () {
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(7)->setTime(16, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'No Show Customer', 'phone' => '081255554444'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
    ]);

    expect($booking->customer->no_show_count)->toBe(0);

    // CONFIRMED -> NO_SHOW
    $this->bookingService->transition($booking, BookingStatusCategory::NO_SHOW);

    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::NO_SHOW);
    expect($booking->fresh()->allocations->first()->status)->toBe(AllocationStatus::RELEASED);
    expect($booking->customer->fresh()->no_show_count)->toBe(1);

    // Owner correction: NO_SHOW -> CONFIRMED
    $this->bookingService->transition($booking, BookingStatusCategory::CONFIRMED, [
        'reason' => 'Owner corrected no-show status',
    ]);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CONFIRMED);
    expect($booking->fresh()->allocations->first()->status)->toBe(AllocationStatus::ACTIVE);
});

test('it releases allocations on CANCELLED or EXPIRED', function () {
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(8)->setTime(13, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Cancel Test', 'phone' => '081244443333'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
    ]);

    $this->bookingService->cancel($booking, 'Customer requested cancellation');

    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CANCELLED);
    expect($booking->fresh()->allocations->first()->status)->toBe(AllocationStatus::RELEASED);
});

test('it rejects invalid transitions in BookingStateMachine with INVALID_TRANSITION 422', function () {
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(9)->setTime(10, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Invalid Test', 'phone' => '081233332222'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
    ]);

    // CONFIRMED cannot jump directly to COMPLETED (must go via CHECKED_IN -> IN_PROGRESS)
    expect(function () use ($booking) {
        $this->bookingService->transition($booking, BookingStatusCategory::COMPLETED);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('INVALID_TRANSITION');
        expect($e->getStatusCode())->toBe(422);
    });

    // Cancel the booking (making it terminal)
    $this->bookingService->cancel($booking);

    // CANCELLED is terminal, cannot transition anywhere
    expect(function () use ($booking) {
        $this->bookingService->transition($booking, BookingStatusCategory::CONFIRMED);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('INVALID_TRANSITION');
    });
});

test('it rolls back transaction completely if a failure occurs during creation', function () {
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(10)->setTime(10, 0)->setTimezone('UTC');

    // Create a time block on the staff to provoke an exception
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->staff->id,
        'start_at' => $slotTime->copy()->subHours(1),
        'end_at' => $slotTime->copy()->addHours(2),
        'reason' => 'Doctor appointment',
    ]);

    expect(function () use ($slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Rollback Cust', 'phone' => '081211112222'],
            'start_at' => $slotTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(BookingException::class);

    // Verify 0 bookings and 0 allocations exist in DB
    expect(Booking::where('tenant_id', $this->tenant->id)->count())->toBe(0);
    expect(BookingAllocation::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

test('it supports rescheduling to a new available slot and frees old slot', function () {
    $slot1 = Carbon::now('Asia/Jakarta')->addDays(11)->setTime(10, 0)->setTimezone('UTC');
    $slot2 = Carbon::now('Asia/Jakarta')->addDays(11)->setTime(14, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Reschedule Test', 'phone' => '081299990000'],
        'start_at' => $slot1,
        'staff_id' => $this->staff->id,
    ]);

    expect($booking->reschedule_count)->toBe(0);

    // Reschedule to slot2
    $rescheduled = $this->bookingService->reschedule($booking, $slot2);

    expect($rescheduled->reschedule_count)->toBe(1);
    expect($rescheduled->start_at->toDateTimeString())->toBe($slot2->toDateTimeString());

    // Slot 1 is now free, another customer can book it!
    $newBooking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'New Customer in Slot 1', 'phone' => '081299991111'],
        'start_at' => $slot1,
        'staff_id' => $this->staff->id,
    ]);

    expect($newBooking)->toBeInstanceOf(Booking::class);
    expect($newBooking->id)->not->toBe($booking->id);
});

test('it rejects booking when tenant is suspended with TENANT_UNAVAILABLE 503', function () {
    $this->tenant->update(['status' => 'SUSPENDED']);
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(12)->setTime(10, 0)->setTimezone('UTC');

    expect(function () use ($slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Suspended Cust', 'phone' => '081299992222'],
            'start_at' => $slotTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('TENANT_UNAVAILABLE');
        expect($e->getStatusCode())->toBe(503);
    });
});

test('it rejects booking when outside business operating hours with OUTSIDE_BUSINESS_HOURS 422', function () {
    // 03:00 in the morning is outside 08:00 - 20:00
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(13)->setTime(3, 0)->setTimezone('UTC');

    expect(function () use ($slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Night Cust', 'phone' => '081299993333'],
            'start_at' => $slotTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('OUTSIDE_BUSINESS_HOURS');
        expect($e->getStatusCode())->toBe(422);
    });
});

test('it rejects booking when minimum advance booking policy is violated', function () {
    $this->business->update([
        'settings' => ['min_advance_hours' => 24],
    ]);

    // Attempting to book in 2 hours
    $slotTime = Carbon::now('Asia/Jakarta')->addHours(2)->setTimezone('UTC');

    expect(function () use ($slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Last Minute Cust', 'phone' => '081299994444'],
            'start_at' => $slotTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('MIN_ADVANCE_NOT_MET');
        expect($e->getStatusCode())->toBe(422);
    });
});

test('it rejects booking when beyond maximum booking horizon policy', function () {
    $this->business->update([
        'settings' => ['max_advance_days' => 14],
    ]);

    // Attempting to book 30 days ahead
    $slotTime = Carbon::now('Asia/Jakarta')->addDays(30)->setTime(10, 0)->setTimezone('UTC');

    expect(function () use ($slotTime) {
        $this->bookingService->create([
            'tenant' => $this->tenant,
            'service' => $this->service,
            'customer' => ['name' => 'Far Future Cust', 'phone' => '081299995555'],
            'start_at' => $slotTime,
            'staff_id' => $this->staff->id,
        ]);
    })->toThrow(function (BookingException $e) {
        expect($e->getErrorCode())->toBe('BEYOND_BOOKING_HORIZON');
        expect($e->getStatusCode())->toBe(422);
    });
});

test('it supports service variants and addons calculating combined duration and price', function () {
    $variant = ServiceVariant::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->service->id,
        'name' => '90 Minutes Deluxe',
        'duration_minutes' => 90,
        'price_idr' => 280000,
        'is_active' => true,
    ]);

    $addon = ServiceAddon::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->service->id,
        'name' => 'Aromatherapy Oil',
        'duration_minutes' => 15,
        'price_idr' => 50000,
        'is_active' => true,
    ]);

    $slotTime = Carbon::now('Asia/Jakarta')->addDays(15)->setTime(10, 0)->setTimezone('UTC');

    $booking = $this->bookingService->create([
        'tenant' => $this->tenant,
        'service' => $this->service,
        'customer' => ['name' => 'Addon Cust', 'phone' => '081299996666'],
        'start_at' => $slotTime,
        'staff_id' => $this->staff->id,
        'variant_id' => $variant->id,
        'addon_ids' => [$addon->id],
        'quantity' => 2,
    ]);

    // Duration: 90 + 15 = 105 minutes.
    // Price: (280000 + 50000) * 2 = 660,000 IDR.
    expect($booking->total_idr)->toBe(660000);
    expect($booking->service_snapshot['duration_minutes'])->toBe(105);
    expect($booking->service_snapshot['total_price_idr'])->toBe(660000);

    // End at: start_at + 105 minutes
    expect($booking->end_at->toDateTimeString())->toBe($slotTime->copy()->addMinutes(105)->toDateTimeString());
});
