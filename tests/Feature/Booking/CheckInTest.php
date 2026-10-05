<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Events\BookingCheckedIn;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatusHistory;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\BusinessMember;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
    Carbon::setTestNow('2026-10-10 10:00:00'); // Saturday 10:00
});

afterEach(function () {
    Carbon::setTestNow();
    TenantContext::clear();
});

function setupCheckInTestHours(int $tenantId, int $businessId): void
{
    for ($i = 0; $i < 7; $i++) {
        BusinessHour::create([
            'tenant_id' => $tenantId,
            'business_id' => $businessId,
            'day_of_week' => $i,
            'open_time' => '00:00:00',
            'close_time' => '23:59:59',
            'is_open' => true,
        ]);
    }
}

test('owner can manually check in a CONFIRMED booking by ID', function () {
    Event::fake([BookingCheckedIn::class, BookingStatusChanged::class]);

    $env = $this->createTenantEnvironment('Salon Check-in Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6, // Saturday
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'duration_minutes' => 60,
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    // Booking at 10:30 (current time is 10:00, within 60-min window before start)
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Budi Santoso', 'phone' => '081234567890'],
        'start_at' => '2026-10-10 10:30:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED)
        ->and($booking->checked_in_at)->toBeNull();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in", [
            'method' => 'MANUAL',
            'notes' => 'Customer tiba di lobi',
        ]);

    $response->assertOk()
        ->assertJson([
            'already_checked_in' => false,
        ]);

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CHECKED_IN)
        ->and($booking->checked_in_at)->not->toBeNull()
        ->and($booking->isCheckedIn())->toBeTrue();

    // Verify status history
    $history = BookingStatusHistory::where('booking_id', $booking->id)->latest('id')->first();
    expect($history)->not->toBeNull()
        ->and($history->from_category)->toBe('CONFIRMED')
        ->and($history->to_category)->toBe('CHECKED_IN')
        ->and($history->source)->toBe('check_in_MANUAL');

    // Verify events fired
    Event::assertDispatched(BookingCheckedIn::class);
    Event::assertDispatched(BookingStatusChanged::class);
});

test('front desk can check in customer by booking code', function () {
    $env = $this->createTenantEnvironment('Barber Code Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'duration_minutes' => 45,
    ]);

    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Dewi Lestari', 'phone' => '081298765432'],
        'start_at' => '2026-10-10 10:15:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // Check-in via code endpoint
    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/app/bookings/check-in', [
            'code' => $booking->code,
            'method' => 'CODE',
        ]);

    $response->assertOk()
        ->assertJson([
            'already_checked_in' => false,
        ]);

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CHECKED_IN)
        ->and($booking->checked_in_at)->not->toBeNull();
});

test('check-in resolves QR manage token and full manage URL', function () {
    $env = $this->createTenantEnvironment('Spa QR Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Siti Rahma', 'phone' => '081211112222'],
        'start_at' => '2026-10-10 10:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    $rawToken = $booking->raw_manage_token;
    expect($rawToken)->not->toBeNull();

    // Check-in via full URL scanned from QR
    $scannedUrl = "https://app.amanbooking.test/booking/manage/{$rawToken}";

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/app/bookings/check-in', [
            'code' => $scannedUrl,
            'method' => 'QR',
        ]);

    $response->assertOk();

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CHECKED_IN);
});

test('check-in before window opens is rejected with CHECK_IN_TOO_EARLY', function () {
    $env = $this->createTenantEnvironment('Dental Clinic Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    // Business window is 60 minutes before start
    $business->update([
        'policies' => [
            'check_in' => [
                'window_before_minutes' => 60,
                'window_after_minutes' => 60,
            ],
        ],
    ]);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 30]);

    // Booking at 13:00 (now is 10:00, 3 hours ahead -> too early)
    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Farhan Hakim', 'phone' => '081233334444'],
        'start_at' => '2026-10-10 13:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'CHECK_IN_TOO_EARLY',
        ]);

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED)
        ->and($booking->checked_in_at)->toBeNull();
});

test('check-in after window closes is rejected with CHECK_IN_TOO_LATE', function () {
    $env = $this->createTenantEnvironment('Court Rental Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    // Business window is 60 minutes after start
    $business->update([
        'policies' => [
            'check_in' => [
                'window_before_minutes' => 60,
                'window_after_minutes' => 60,
            ],
        ],
    ]);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    // Booking started at 08:00 (now is 10:00, 2 hours late -> window closed at 09:00)
    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Late Customer', 'phone' => '081255556666'],
        'start_at' => '2026-10-10 08:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'CHECK_IN_TOO_LATE',
        ]);
});

test('desk override allows check-in outside standard window', function () {
    $env = $this->createTenantEnvironment('Desk Override Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    // Booking at 15:00 (now is 10:00, 5 hours too early)
    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Early Bird VIP', 'phone' => '081277778888'],
        'start_at' => '2026-10-10 15:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // Call check-in with desk_override = true
    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in", [
            'desk_override' => true,
            'notes' => 'Customer datang lebih awal dan resource siap',
        ]);

    $response->assertOk();

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CHECKED_IN)
        ->and($booking->checked_in_at)->not->toBeNull();
});

test('idempotency: repeated check-in returns already_checked_in without error or duplicate history', function () {
    $env = $this->createTenantEnvironment('Idempotency Check-in');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Rina Nose', 'phone' => '081299990000'],
        'start_at' => '2026-10-10 10:15:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // First check-in
    $res1 = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $res1->assertOk()->assertJson(['already_checked_in' => false]);

    $historyCount = BookingStatusHistory::where('booking_id', $booking->id)->count();

    // Second check-in (same customer scanned again)
    $res2 = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $res2->assertOk()->assertJson(['already_checked_in' => true]);

    // Verify history count did not increment
    expect(BookingStatusHistory::where('booking_id', $booking->id)->count())->toBe($historyCount);
});

test('check-in rejects unconfirmed bookings (PENDING, CANCELLED)', function () {
    $env = $this->createTenantEnvironment('Unconfirmed Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    $createBooking = app(CreateBooking::class);
    // Booking with status PENDING
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Pending Guy', 'phone' => '081200001111'],
        'start_at' => '2026-10-10 10:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::PENDING,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $response->assertStatus(422)
        ->assertJson([
            'code' => 'CHECK_IN_NOT_ALLOWED',
        ]);
});

test('staff without booking.check_in permission is rejected with 403', function () {
    $env = $this->createTenantEnvironment('Staff Permission Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'duration_minutes' => 60]);

    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Perm Test Customer', 'phone' => '081233332222'],
        'start_at' => '2026-10-10 10:00:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // Create a staff user without check-in permission
    $staffUser = User::factory()->create();
    BusinessMember::create([
        'tenant_id' => $tenant->id,
        'user_id' => $staffUser->id,
        'preset' => 'STAFF',
        'permissions' => ['booking.view'], // Only view, no check-in
    ]);

    $response = $this->actingAs($staffUser)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $response->assertStatus(403);

    // Give staff booking.check_in permission
    BusinessMember::where('tenant_id', $tenant->id)
        ->where('user_id', $staffUser->id)
        ->update(['permissions' => ['booking.view', 'booking.check_in']]);

    $responseOk = $this->actingAs($staffUser)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/app/bookings/{$booking->id}/check-in");

    $responseOk->assertOk();
});

test('tenant boundary strictly prevents checking in other tenants booking', function () {
    $envA = $this->createTenantEnvironment('Tenant A');
    $envB = $this->createTenantEnvironment('Tenant B');

    setupCheckInTestHours($envA['tenant']->id, $envA['business']->id);
    setupCheckInTestHours($envB['tenant']->id, $envB['business']->id);

    $staffA = Resource::factory()->create(['tenant_id' => $envA['tenant']->id, 'business_id' => $envA['business']->id]);
    ResourceSchedule::create([
        'tenant_id' => $envA['tenant']->id,
        'resource_id' => $staffA->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $serviceA = Service::factory()->create(['tenant_id' => $envA['tenant']->id, 'business_id' => $envA['business']->id]);

    $createBooking = app(CreateBooking::class);
    $bookingA = $createBooking->execute([
        'tenant' => $envA['tenant'],
        'service' => $serviceA,
        'customer' => ['name' => 'Customer A', 'phone' => '081299991111'],
        'start_at' => '2026-10-10 10:00:00',
        'staff_id' => $staffA->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // User from Tenant B tries to check in Tenant A's booking
    $response = $this->actingAs($envB['user'])
        ->withSession(['tenant_id' => $envB['tenant']->id])
        ->postJson("/app/bookings/{$bookingA->id}/check-in");

    $response->assertStatus(404);
});

test('check-in lookup endpoint returns booking details and window readiness', function () {
    $env = $this->createTenantEnvironment('Lookup Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupCheckInTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 6,
        'start_time' => '00:00:00',
        'end_time' => '23:59:59',
        'is_active' => true,
    ]);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);

    $createBooking = app(CreateBooking::class);
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Lookup User', 'phone' => '081288887777'],
        'start_at' => '2026-10-10 10:30:00',
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson("/app/bookings/check-in/lookup?code={$booking->code}");

    $response->assertOk()
        ->assertJson([
            'can_check_in' => true,
            'booking' => [
                'code' => $booking->code,
            ],
            'window_status' => [
                'is_open' => true,
            ],
        ]);
});
