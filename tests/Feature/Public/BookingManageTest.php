<?php

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-04 10:00:00');

    $this->tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Barbershop Premium Jakarta',
        'slug' => 'barber-premium',
        'timezone' => 'Asia/Jakarta',
        'whatsapp' => '081299887766',
        'address' => 'Jl. Senopati No. 18, Jakarta Selatan',
        'published_at' => Carbon::now()->subDays(10),
        'booking_rules' => [
            'reschedule_deadline_hours' => 2,
            'cancellation_deadline_hours' => 2,
            'max_reschedule_times' => 2,
        ],
    ]);

    // Setup 7 operating days (09:00 - 21:00)
    for ($day = 0; $day <= 6; $day++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '21:00:00',
            'breaks' => [],
        ]);
    }

    $this->resourceType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'STAFF',
        'name' => 'Barber',
        'is_staff' => true,
    ]);

    $this->staff = Resource::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->resourceType->id,
        'name' => 'Budi Capster',
        'code' => 'BC-01',
        'capacity' => 1,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    $this->service = Service::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Gentleman Haircut & Shave',
        'duration_minutes' => 60,
        'price_idr' => 150000,
        'capacity' => 1,
        'buffer_before' => 0,
        'buffer_after' => 0,
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Ahmad Dani',
        'phone_e164' => '+628123456789',
        'email' => 'ahmad@example.com',
    ]);

    $this->rawManageToken = Str::random(40);
    $this->startAtUtc = Carbon::parse('2026-10-06 14:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    $this->endAtUtc = $this->startAtUtc->copy()->addMinutes(60);

    $this->booking = Booking::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BK-20261006-00001',
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'service_snapshot' => [
            'service_id' => $this->service->id,
            'name' => $this->service->name,
            'duration_minutes' => 60,
            'price_idr' => 150000,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'capacity' => 1,
        ],
        'start_at' => $this->startAtUtc,
        'end_at' => $this->endAtUtc,
        'business_timezone' => 'Asia/Jakarta',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'UNPAID',
        'total_idr' => 150000,
        'deposit_idr' => 0,
        'source' => 'PUBLIC_WEB',
        'reschedule_count' => 0,
        'manage_token' => hash('sha256', $this->rawManageToken),
        'manage_token_expires_at' => Carbon::now()->addDays(30),
    ]);

    $this->allocation = BookingAllocation::create([
        'tenant_id' => $this->tenant->id,
        'booking_id' => $this->booking->id,
        'resource_id' => $this->staff->id,
        'role' => 'staff',
        'start_at' => $this->startAtUtc,
        'end_at' => $this->endAtUtc,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('customer can view booking confirmation success page with correct details and qr code data', function () {
    $response = $this->get("/{$this->business->slug}/booking/success/{$this->booking->code}?token={$this->rawManageToken}");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/BookingSuccess')
        ->where('booking.code', 'BK-20261006-00001')
        ->where('booking.status', 'CONFIRMED')
        ->where('booking.manage_token', $this->rawManageToken)
        ->where('booking.staff_name', 'Budi Capster')
        ->where('booking.service.name', 'Gentleman Haircut & Shave')
        ->where('business.name', 'Barbershop Premium Jakarta')
    );
});

test('customer can download calendar ics file from success endpoint', function () {
    $response = $this->get("/{$this->business->slug}/booking/success/{$this->booking->code}/calendar.ics");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="reservasi-BK-20261006-00001.ics"');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('UID:BK-20261006-00001@amanbooking.com')
        ->toContain('SUMMARY:Gentleman Haircut & Shave - Barbershop Premium Jakarta')
        ->toContain('LOCATION:Jl. Senopati No. 18, Jakarta Selatan')
        ->toContain('END:VCALENDAR');
});

test('customer can view booking manage page using raw manage token', function () {
    $response = $this->get("/{$this->business->slug}/booking/manage/{$this->rawManageToken}");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/BookingManage')
        ->where('booking.code', 'BK-20261006-00001')
        ->where('booking.status', 'CONFIRMED')
        ->where('policy.can_reschedule', true)
        ->where('policy.can_cancel', true)
        ->where('policy.reschedules_remaining', 2)
    );
});

test('customer cannot view booking manage page with invalid token', function () {
    $response = $this->get("/{$this->business->slug}/booking/manage/invalid-token-12345");

    $response->assertStatus(404);
});

test('customer cannot view booking manage page with expired manage token', function () {
    $this->booking->update([
        'manage_token_expires_at' => Carbon::now()->subMinutes(10),
    ]);

    $response = $this->get("/{$this->business->slug}/booking/manage/{$this->rawManageToken}");

    $response->assertStatus(410);
});

test('customer cannot access booking manage page from different tenant slug', function () {
    $otherTenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $otherBusiness = Business::factory()->create([
        'tenant_id' => $otherTenant->id,
        'slug' => 'other-business',
        'published_at' => Carbon::now()->subDays(5),
    ]);

    $response = $this->get("/{$otherBusiness->slug}/booking/manage/{$this->rawManageToken}");

    $response->assertStatus(404);
});

test('customer can download calendar ics file from manage endpoint', function () {
    $response = $this->get("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/calendar.ics");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    expect($response->getContent())->toContain('UID:BK-20261006-00001@amanbooking.com');
});

test('customer can reschedule booking to a valid future available slot', function () {
    // New slot: 2026-10-07 10:00:00 Asia/Jakarta
    $newSlotLocal = '2026-10-07 10:00:00';
    $newSlotUtc = Carbon::parse($newSlotLocal, 'Asia/Jakarta')->setTimezone('UTC');

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/reschedule", [
        'new_start_at' => $newSlotLocal,
        'reason' => 'Ada jadwal meeting mendadak',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Jadwal reservasi Anda berhasil diubah.',
        'booking' => [
            'code' => 'BK-20261006-00001',
            'reschedule_count' => 1,
        ],
    ]);

    $this->booking->refresh();
    expect($this->booking->start_at->toDateTimeString())->toBe($newSlotUtc->toDateTimeString())
        ->and($this->booking->reschedule_count)->toBe(1)
        ->and($this->booking->status_category)->toBe(BookingStatusCategory::CONFIRMED);

    // Verify allocations updated
    $this->allocation->refresh();
    expect($this->allocation->start_at->toDateTimeString())->toBe($newSlotUtc->toDateTimeString())
        ->and($this->allocation->status)->toBe(AllocationStatus::ACTIVE);

    // Verify status history recorded
    $this->assertDatabaseHas('booking_status_history', [
        'booking_id' => $this->booking->id,
        'to_category' => 'CONFIRMED',
        'reason' => 'Ada jadwal meeting mendadak',
    ]);
});

test('customer reschedule is rejected when new slot is already taken by another booking', function () {
    $conflictStartUtc = Carbon::parse('2026-10-07 10:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    $conflictEndUtc = $conflictStartUtc->copy()->addMinutes(60);

    // Create another booking and allocation overlapping the new slot
    $otherBooking = Booking::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BK-20261007-00002',
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'service_snapshot' => $this->booking->service_snapshot,
        'start_at' => $conflictStartUtc,
        'end_at' => $conflictEndUtc,
        'business_timezone' => 'Asia/Jakarta',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'UNPAID',
        'total_idr' => 150000,
        'source' => 'PUBLIC_WEB',
    ]);

    BookingAllocation::create([
        'tenant_id' => $this->tenant->id,
        'booking_id' => $otherBooking->id,
        'resource_id' => $this->staff->id,
        'role' => 'staff',
        'start_at' => $conflictStartUtc,
        'end_at' => $conflictEndUtc,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/reschedule", [
        'new_start_at' => '2026-10-07 10:00:00',
    ]);

    $response->assertStatus(409);
    $response->assertJson([
        'code' => 'SLOT_TAKEN',
    ]);
});

test('customer reschedule is rejected when new slot has time block on staff resource', function () {
    $blockStartUtc = Carbon::parse('2026-10-07 10:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    $blockEndUtc = $blockStartUtc->copy()->addMinutes(120);

    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->staff->id,
        'start_at' => $blockStartUtc,
        'end_at' => $blockEndUtc,
        'reason' => 'Staff training',
    ]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/reschedule", [
        'new_start_at' => '2026-10-07 10:00:00',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'code' => 'RESOURCE_UNAVAILABLE_FOR_FULL_DURATION',
    ]);
});

test('customer reschedule is rejected when max reschedule limit is reached', function () {
    $this->booking->update(['reschedule_count' => 2]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/reschedule", [
        'new_start_at' => '2026-10-08 11:00:00',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'code' => 'RESCHEDULE_LIMIT_REACHED',
        'message' => 'Batas perubahan jadwal untuk booking ini sudah tercapai.',
    ]);
});

test('customer reschedule is rejected when reschedule deadline has passed', function () {
    // Current test time is 2026-10-04 10:00:00.
    // Set booking start_at to 2026-10-04 11:00:00 (only 1 hour away; policy requires 2 hours)
    $this->booking->update([
        'start_at' => Carbon::now()->addHours(1),
        'end_at' => Carbon::now()->addHours(2),
    ]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/reschedule", [
        'new_start_at' => '2026-10-08 14:00:00',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'code' => 'RESCHEDULE_DEADLINE_PASSED',
        'message' => 'Batas waktu perubahan jadwal sudah lewat. Silakan hubungi bisnis.',
    ]);
});

test('customer can cancel booking within cancellation policy and allocations are released', function () {
    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/cancel", [
        'reason' => 'Ada urusan keluarga mendadak',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'message' => 'Reservasi Anda telah berhasil dibatalkan.',
        'booking' => [
            'code' => 'BK-20261006-00001',
            'status' => 'CANCELLED',
        ],
    ]);

    $this->booking->refresh();
    expect($this->booking->status_category)->toBe(BookingStatusCategory::CANCELLED);

    // Verify allocations released
    $this->allocation->refresh();
    expect($this->allocation->status)->toBe(AllocationStatus::RELEASED);

    // Verify status history
    $this->assertDatabaseHas('booking_status_history', [
        'booking_id' => $this->booking->id,
        'from_category' => 'CONFIRMED',
        'to_category' => 'CANCELLED',
        'reason' => 'Ada urusan keluarga mendadak',
    ]);
});

test('customer cancel is rejected when cancellation deadline has passed', function () {
    // Set booking start_at to 1 hour away (deadline is 2 hours)
    $this->booking->update([
        'start_at' => Carbon::now()->addHours(1),
        'end_at' => Carbon::now()->addHours(2),
    ]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/cancel", [
        'reason' => 'Batal mendadak',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'code' => 'CANCEL_DEADLINE_PASSED',
        'message' => 'Batas waktu pembatalan sudah lewat. Silakan hubungi bisnis.',
    ]);
});

test('customer cancel is rejected if booking is already cancelled', function () {
    $this->booking->update([
        'status_category' => BookingStatusCategory::CANCELLED,
    ]);

    $response = $this->postJson("/{$this->business->slug}/booking/manage/{$this->rawManageToken}/cancel");

    $response->assertStatus(422);
    $response->assertJson([
        'code' => 'INVALID_TRANSITION',
    ]);
});

test('api public aliases for reschedule and cancel work identically', function () {
    $response = $this->postJson("/api/public/{$this->business->slug}/bookings/{$this->rawManageToken}/cancel", [
        'reason' => 'Via public API alias',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'booking' => [
            'status' => 'CANCELLED',
        ],
    ]);
});
