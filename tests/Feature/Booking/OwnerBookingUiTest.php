<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('owner can access bookings index page across table, calendar, and kanban views', function () {
    $env = $this->createTenantEnvironment('Aesthetic Clinic');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Skin Treatment']);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // 1. Table view
    $response = $this->actingAs($owner)->get(route('owner.bookings.index', ['view' => 'table']));
    $response->assertStatus(200);

    // 2. Calendar view
    $calResponse = $this->actingAs($owner)->get(route('owner.bookings.index', ['view' => 'calendar']));
    $calResponse->assertStatus(200);

    // 3. Kanban view
    $kanbanResponse = $this->actingAs($owner)->get(route('owner.bookings.index', ['view' => 'kanban']));
    $kanbanResponse->assertStatus(200);
});

test('owner can query slots for quick booking via AvailabilityService', function () {
    $env = $this->createTenantEnvironment('Spa Oasis');
    $owner = $env['user'];
    $tenant = $env['tenant'];
    $business = $env['business'];

    // Setup 7-day business hours 09:00 - 18:00
    for ($i = 0; $i < 7; $i++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $i,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_open' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'duration_minutes' => 60,
        'buffer_before' => 0,
        'buffer_after' => 0,
    ]);

    $date = now()->addDays(2)->format('Y-m-d');

    $response = $this->actingAs($owner)->getJson(route('owner.bookings.slots', [
        'service_id' => $service->id,
        'date' => $date,
    ]));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'service_id',
        'date',
        'slots',
    ]);
});

test('owner can create quick booking for new customer with auto customer resolution', function () {
    $env = $this->createTenantEnvironment('Barber Hub');
    $owner = $env['user'];
    $tenant = $env['tenant'];
    $business = $env['business'];

    for ($i = 0; $i < 7; $i++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $i,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
            'is_open' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'duration_minutes' => 45,
        'price_idr' => 75000,
    ]);

    $startAt = now()->addDays(2)->setTime(10, 0)->toIso8601String();

    $response = $this->actingAs($owner)->postJson(route('owner.bookings.store'), [
        'service_id' => $service->id,
        'start_at' => $startAt,
        'customer_name' => 'Dimas Anggara',
        'customer_phone' => '0812-3344-5566',
        'customer_email' => 'dimas@example.com',
        'payment_status' => 'PAID',
        'notes' => 'Permintaan potong gaya fade.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'booking' => ['id', 'code', 'customer_id', 'service_id', 'status_category'],
    ]);

    // Customer was resolved and normalized to E.164
    $customer = Customer::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('email', 'dimas@example.com')
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->phone_e164)->toBe('+6281233445566');
});

test('feed endpoint returns background sync data for polling', function () {
    $env = $this->createTenantEnvironment('Dental Hub');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
    ]);

    $response = $this->actingAs($owner)->getJson(route('owner.bookings.feed', [
        'view' => 'table',
    ]));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'bookings',
        'timestamp',
    ]);
});

test('owner can execute valid status transition and invalid transition is rejected', function () {
    $env = $this->createTenantEnvironment('Massage Reflexology');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // 1. Valid transition: CONFIRMED -> CHECKED_IN
    $validResponse = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'CHECKED_IN',
        'reason' => 'Customer sudah hadir di lobi.',
    ]);
    $validResponse->assertStatus(200);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CHECKED_IN);

    // 2. Invalid transition: CHECKED_IN cannot jump directly to NO_SHOW (PRD 213)
    $invalidResponse = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'NO_SHOW',
    ]);
    $invalidResponse->assertStatus(422);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CHECKED_IN);
});

test('owner can reschedule booking and conflict returns 422 error triggering rollback', function () {
    $env = $this->createTenantEnvironment('Studio Photo');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer1 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'duration_minutes' => 60,
    ]);

    $resource = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Studio Room A',
    ]);

    $t1Start = now()->addDays(3)->setTime(10, 0);
    $t1End = $t1Start->copy()->addMinutes(60);

    // Booking 1 allocated to Studio Room A at 10:00 - 11:00
    $b1 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer1->id,
        'service_id' => $service->id,
        'start_at' => $t1Start,
        'end_at' => $t1End,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $b1->id,
        'resource_id' => $resource->id,
        'start_at' => $t1Start,
        'end_at' => $t1End,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);

    // Booking 2 at 14:00 - 15:00
    $t2Start = now()->addDays(3)->setTime(14, 0);
    $t2End = $t2Start->copy()->addMinutes(60);

    $b2 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer2->id,
        'service_id' => $service->id,
        'start_at' => $t2Start,
        'end_at' => $t2End,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $b2->id,
        'resource_id' => $resource->id,
        'start_at' => $t2Start,
        'end_at' => $t2End,
        'status' => AllocationStatus::ACTIVE,
        'quantity' => 1,
    ]);

    // Attempt to reschedule b2 into b1's slot (10:00) -> Should fail with 422 conflict!
    $conflictResponse = $this->actingAs($owner)->postJson(route('owner.bookings.reschedule', $b2->id), [
        'new_start_at' => $t1Start->toIso8601String(),
    ]);

    $conflictResponse->assertStatus(422);
    $conflictResponse->assertJsonStructure(['message']);
    expect($b2->fresh()->start_at->toDateTimeString())->toBe($t2Start->toDateTimeString());

    // Reschedule b2 into an open slot (16:00) -> Should succeed!
    $openSlot = now()->addDays(3)->setTime(16, 0);
    $successResponse = $this->actingAs($owner)->postJson(route('owner.bookings.reschedule', $b2->id), [
        'new_start_at' => $openSlot->toIso8601String(),
    ]);

    $successResponse->assertStatus(200);
    expect($b2->fresh()->start_at->toDateTimeString())->toBe($openSlot->toDateTimeString());
});

test('tenant boundary strictly prevents accessing or modifying other tenant bookings', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $ownerA = $envA['user'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    $tenantB = $envB['tenant'];

    $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

    $bookingB = Booking::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $customerB->id,
        'service_id' => $serviceB->id,
    ]);

    // Owner A cannot view booking of Tenant B -> 404
    $response = $this->actingAs($ownerA)->getJson(route('owner.bookings.show', $bookingB->id));
    $response->assertStatus(404);

    // Owner A cannot transition status of booking of Tenant B -> 404
    $statusResponse = $this->actingAs($ownerA)->postJson(route('owner.bookings.status', $bookingB->id), [
        'status' => 'CONFIRMED',
    ]);
    $statusResponse->assertStatus(404);

    // Owner A cannot reschedule booking of Tenant B -> 404
    $rescheduleResponse = $this->actingAs($ownerA)->postJson(route('owner.bookings.reschedule', $bookingB->id), [
        'new_start_at' => now()->addDay()->toIso8601String(),
    ]);
    $rescheduleResponse->assertStatus(404);
});
