<?php

use App\Domain\Availability\Services\AvailabilityService;
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
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
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
    $this->availabilityService = new AvailabilityService($this->calendarService);
});

test('handles Jayapura morning booking crossing midnight UTC date boundary with accurate local code', function () {
    // Jayapura is UTC+9 (WIT).
    // Opening at 08:30 WIT on 2026-10-15 corresponds to 2026-10-14 23:30:00 UTC (previous day in UTC!).
    $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jayapura',
        'settings' => [
            'min_advance_hours' => 0,
            'max_advance_days' => 90,
        ],
    ]);

    // Open every day 08:00 - 18:00 WIT
    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '18:00:00',
            'breaks' => [],
        ]);
    }

    $staffType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);
    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $staffType->id,
        'name' => 'Dokter Papua',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Konsultasi Pagi Jayapura',
        'duration_minutes' => 60,
        'price_idr' => 250000,
        'capacity' => 1,
        'buffer_before' => 0,
        'buffer_after' => 0,
    ]);

    // Customer books 2026-10-15 08:30:00 WIT -> in UTC: 2026-10-14 23:30:00
    $localStartStr = '2026-10-15 08:30:00';
    $utcStart = Carbon::parse($localStartStr, 'Asia/Jayapura')->setTimezone('UTC');

    expect($utcStart->format('Y-m-d H:i:s'))->toBe('2026-10-14 23:30:00');

    // Execute booking
    $booking = $this->createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => [
            'name' => 'Edison Jayapura',
            'phone' => '081299887766',
        ],
        'start_at' => $utcStart->toIso8601String(),
        'staff_id' => $staff->id,
    ]);

    // Verify booking attributes
    expect($booking->status_category->value)->toBe('CONFIRMED')
        ->and($booking->start_at->toIso8601String())->toBe('2026-10-14T23:30:00+00:00')
        ->and($booking->end_at->toIso8601String())->toBe('2026-10-15T00:30:00+00:00');

    // Local projection must be October 15, 2026 08:30 WIT
    $localProjection = $booking->start_at->copy()->setTimezone('Asia/Jayapura');
    expect($localProjection->format('Y-m-d H:i:s'))->toBe('2026-10-15 08:30:00');

    // Booking code must match local business date (BK-20261015-00001, NOT BK-20261014-00001)
    expect($booking->code)->toBe('BK-20261015-00001');

    // Active allocation must be locked for that slot in UTC
    $allocation = BookingAllocation::where('booking_id', $booking->id)->first();
    expect($allocation)->not->toBeNull()
        ->and($allocation->start_at->toIso8601String())->toBe('2026-10-14T23:30:00+00:00')
        ->and($allocation->end_at->toIso8601String())->toBe('2026-10-15T00:30:00+00:00');

    // Attempting double booking at overlapping time (e.g. 09:00 WIT = 2026-10-15 00:00:00 UTC) must be rejected with SLOT_TAKEN
    $conflictUtc = Carbon::parse('2026-10-15 09:00:00', 'Asia/Jayapura')->setTimezone('UTC');
    expect(fn () => $this->createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Penyerobot Slot', 'phone' => '081211112222'],
        'start_at' => $conflictUtc->toIso8601String(),
        'staff_id' => $staff->id,
    ]))->toThrow(BookingException::class, 'baru saja dipesan customer lain');
});

test('handles exact midnight UTC boundary in Makassar (WITA, UTC+8)', function () {
    // Makassar is UTC+8.
    // 08:00:00 WITA on 2026-11-20 is EXACTLY 00:00:00 UTC on 2026-11-20 (Midnight UTC!).
    $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Makassar',
        'settings' => [
            'min_advance_hours' => 0,
            'max_advance_days' => 90,
        ],
    ]);

    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
            'breaks' => [],
        ]);
    }

    $staffType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);
    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $staffType->id,
        'name' => 'Barber Daeng Makassar',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cukur Pagi Midnight UTC',
        'duration_minutes' => 45,
        'price_idr' => 75000,
        'capacity' => 1,
    ]);

    $witaStart = Carbon::parse('2026-11-20 08:00:00', 'Asia/Makassar');
    $utcStart = $witaStart->copy()->setTimezone('UTC');

    expect($utcStart->format('Y-m-d H:i:s'))->toBe('2026-11-20 00:00:00');

    $booking = $this->createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Andi Makassar', 'phone' => '082199881122'],
        'start_at' => $utcStart->toIso8601String(),
        'staff_id' => $staff->id,
    ]);

    expect($booking->status_category->value)->toBe('CONFIRMED')
        ->and($booking->start_at->toIso8601String())->toBe('2026-11-20T00:00:00+00:00')
        ->and($booking->end_at->toIso8601String())->toBe('2026-11-20T00:45:00+00:00')
        ->and($booking->code)->toBe('BK-20261120-00001');

    // Local projection
    expect($booking->start_at->copy()->setTimezone('Asia/Makassar')->format('H:i'))->toBe('08:00');
});

test('handles Jakarta (WIB, UTC+7) evening bookings and cross-timezone customer interaction', function () {
    // Jakarta is UTC+7.
    // 20:30:00 WIB on 2026-12-01 is 13:30:00 UTC.
    $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jakarta',
        'settings' => [
            'min_advance_hours' => 0,
            'max_advance_days' => 90,
        ],
    ]);

    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '10:00:00',
            'close_time' => '22:00:00',
            'breaks' => [],
        ]);
    }

    $staffType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);
    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $staffType->id,
        'name' => 'Therapist Jakarta',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Spa Malam Jakarta',
        'duration_minutes' => 60,
        'price_idr' => 200000,
        'capacity' => 1,
    ]);

    // Customer in Jayapura (WIT) books at their 22:30 WIT (which is 20:30 WIB Jakarta, 13:30 UTC)
    $customerJayapuraTime = Carbon::parse('2026-12-01 22:30:00', 'Asia/Jayapura');
    $utcStart = $customerJayapuraTime->copy()->setTimezone('UTC');

    expect($utcStart->format('Y-m-d H:i:s'))->toBe('2026-12-01 13:30:00');

    $booking = $this->createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Turis Jayapura', 'phone' => '081344556677'],
        'start_at' => $utcStart->toIso8601String(),
        'staff_id' => $staff->id,
    ]);

    expect($booking->status_category->value)->toBe('CONFIRMED')
        ->and($booking->code)->toBe('BK-20261201-00001');

    // Verified in Jakarta local time
    $jakartaTime = $booking->start_at->copy()->setTimezone('Asia/Jakarta');
    expect($jakartaTime->format('H:i'))->toBe('20:30')
        ->and($jakartaTime->format('Y-m-d'))->toBe('2026-12-01');
});

test('AvailabilityService correctly resolves slots across midnight UTC for Jayapura business', function () {
    $tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jayapura',
    ]);

    // Monday to Sunday: 08:00 - 12:00 WIT
    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '12:00:00',
            'breaks' => [],
        ]);
    }

    $staffType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'staff',
        'name' => 'Staff',
        'is_staff' => true,
    ]);
    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $staffType->id,
        'name' => 'Staff Jayapura',
        'state' => 'AVAILABLE',
    ]);

    for ($d = 0; $d <= 6; $d++) {
        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $staff->id,
            'day_of_week' => $d,
            'is_available' => true,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'breaks' => [],
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Layanan Pagi',
        'duration_minutes' => 60,
        'capacity' => 1,
    ]);

    // Query slots for local date 2026-10-15
    $slots = $this->availabilityService->getSlotsForDate(
        $tenant,
        $service,
        '2026-10-15',
        ['slot_step_minutes' => 60]
    );

    // The first slot should be 08:00 WIT
    $firstSlot = $slots->firstWhere('start_time', '08:00');
    expect($firstSlot)->not->toBeNull()
        ->and($firstSlot['date'])->toBe('2026-10-15')
        ->and($firstSlot['start_time'])->toBe('08:00')
        ->and($firstSlot['end_time'])->toBe('09:00')
        ->and($firstSlot['is_available'])->toBeTrue();

    // Verify UTC equivalence: 08:00 WIT on 2026-10-15 is 23:00 UTC on 2026-10-14!
    $slotUtc = Carbon::parse($firstSlot['start_at'])->setTimezone('UTC');
    expect($slotUtc->format('Y-m-d H:i:s'))->toBe('2026-10-14 23:00:00');
});
