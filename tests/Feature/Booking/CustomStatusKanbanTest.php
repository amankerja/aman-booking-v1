<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Booking\Services\BookingStatusService;
use App\Domain\Customer\Models\Customer;
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

test('it seeds default 7 custom statuses for tenant when requested (PRD 24, 25, 213)', function () {
    $env = $this->createTenantEnvironment('Batavia Barbershop');
    $tenant = $env['tenant'];

    $service = app(BookingStatusService::class);
    $statuses = $service->getStatusesForTenant($tenant);

    expect($statuses)->toHaveCount(7);
    expect($statuses->pluck('category')->map->value->all())->toContain(
        'PENDING',
        'CONFIRMED',
        'CHECKED_IN',
        'IN_PROGRESS',
        'COMPLETED',
        'CANCELLED',
        'NO_SHOW'
    );

    // Verify icons and non-empty color attributes for accessibility
    foreach ($statuses as $status) {
        expect($status->name)->not->toBeEmpty();
        expect($status->icon)->not->toBeEmpty();
        expect($status->color)->toStartWith('#');
        expect($status->sort_order)->toBeGreaterThan(0);
    }
});

test('owner can access custom statuses settings page and see system categories', function () {
    $env = $this->createTenantEnvironment('Glow Salon');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->get(route('owner.settings.statuses.index'));
    $response->assertStatus(200);

    // Also supports JSON request
    $jsonResponse = $this->actingAs($owner)->getJson(route('owner.settings.statuses.index'));
    $jsonResponse->assertStatus(200);
    $jsonResponse->assertJsonStructure([
        'statuses' => [
            '*' => ['id', 'tenant_id', 'name', 'slug', 'category', 'color', 'icon', 'sort_order'],
        ],
    ]);
});

test('owner can create a custom status mapped to a valid category', function () {
    $env = $this->createTenantEnvironment('Sports Center');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->postJson(route('owner.settings.statuses.store'), [
        'name' => 'Menunggu Pembayaran DP',
        'category' => 'PENDING',
        'color' => '#d97706',
        'icon' => 'clock',
        'description' => 'Customer perlu membayar deposit sewa lapangan.',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('status.name', 'Menunggu Pembayaran DP');
    $response->assertJsonPath('status.category', 'PENDING');

    $statusInDb = BookingStatus::where('tenant_id', $tenant->id)
        ->where('name', 'Menunggu Pembayaran DP')
        ->first();

    expect($statusInDb)->not->toBeNull();
    expect($statusInDb->category)->toBe(BookingStatusCategory::PENDING);
    expect($statusInDb->slug)->toBe('menunggu-pembayaran-dp');
});

test('owner can update, reorder, and delete custom status when not in use', function () {
    $env = $this->createTenantEnvironment('Aroma Spa');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $status = BookingStatus::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tahap Awal',
        'category' => BookingStatusCategory::CONFIRMED,
        'sort_order' => 1,
    ]);

    // 1. Update
    $updateResponse = $this->actingAs($owner)->putJson(route('owner.settings.statuses.update', $status->id), [
        'name' => 'Tahap Awal Diperbarui',
        'color' => '#059669',
    ]);
    $updateResponse->assertStatus(200);
    expect($status->fresh()->name)->toBe('Tahap Awal Diperbarui');

    // 2. Reorder
    $status2 = BookingStatus::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tahap Dua',
        'category' => BookingStatusCategory::IN_PROGRESS,
        'sort_order' => 2,
    ]);

    $reorderResponse = $this->actingAs($owner)->postJson(route('owner.settings.statuses.reorder'), [
        'ordered_ids' => [$status2->id, $status->id],
    ]);
    $reorderResponse->assertStatus(200);
    expect($status2->fresh()->sort_order)->toBe(1);
    expect($status->fresh()->sort_order)->toBe(2);

    // 3. Delete
    $deleteResponse = $this->actingAs($owner)->deleteJson(route('owner.settings.statuses.destroy', $status->id));
    $deleteResponse->assertStatus(200);
    expect(BookingStatus::find($status->id))->toBeNull();
});

test('deleting a custom status that is in use by active booking is rejected with 422 (safeguard)', function () {
    $env = $this->createTenantEnvironment('Photo Studio');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $status = BookingStatus::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Sesi Foto',
        'category' => BookingStatusCategory::IN_PROGRESS,
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::IN_PROGRESS,
        'status_id' => (string) $status->id,
    ]);

    $deleteResponse = $this->actingAs($owner)->deleteJson(route('owner.settings.statuses.destroy', $status->id));
    $deleteResponse->assertStatus(422);
    $deleteResponse->assertJsonFragment([
        'message' => "Status 'Sesi Foto' sedang digunakan oleh booking aktif dan tidak dapat dihapus. Anda dapat menonaktifkannya.",
    ]);

    expect(BookingStatus::find($status->id))->not->toBeNull();
});

test('kanban view renders custom statuses and bookings with tenant isolation', function () {
    $envA = $this->createTenantEnvironment('Tenant Barber A');
    $ownerA = $envA['user'];
    $tenantA = $envA['tenant'];

    $envB = $this->createTenantEnvironment('Tenant Clinic B');
    $tenantB = $envB['tenant'];

    // Tenant A custom status
    $statusA = BookingStatus::factory()->create([
        'tenant_id' => $tenantA->id,
        'name' => 'Potong Rambut',
        'category' => BookingStatusCategory::IN_PROGRESS,
    ]);

    // Tenant B custom status
    $statusB = BookingStatus::factory()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Konsultasi Dokter',
        'category' => BookingStatusCategory::IN_PROGRESS,
    ]);

    $response = $this->actingAs($ownerA)->get(route('owner.bookings.index', ['view' => 'kanban']));
    $response->assertStatus(200);

    // Verify Inertia props contain Tenant A statuses, not Tenant B
    $inertiaStatuses = $response->viewData('page')['props']['statuses'] ?? [];
    $statusNames = collect($inertiaStatuses)->pluck('name')->all();

    expect($statusNames)->toContain('Potong Rambut');
    expect($statusNames)->not->toContain('Konsultasi Dokter');
});

test('dragging booking on kanban triggers transitionStatus and updates both status_category and status_id', function () {
    $env = $this->createTenantEnvironment('Barber Hub');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $confirmedStatus = BookingStatus::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Terkonfirmasi',
        'category' => BookingStatusCategory::CONFIRMED,
    ]);

    $checkedInStatus = BookingStatus::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Customer Tiba',
        'category' => BookingStatusCategory::CHECKED_IN,
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'status_id' => (string) $confirmedStatus->id,
    ]);

    // Drag from CONFIRMED -> CHECKED_IN
    $response = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'CHECKED_IN',
        'status_id' => $checkedInStatus->id,
        'reason' => 'Customer tiba di lokasi.',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('booking.status_category', 'CHECKED_IN');

    $freshBooking = $booking->fresh();
    expect($freshBooking->status_category)->toBe(BookingStatusCategory::CHECKED_IN);
    expect($freshBooking->status_id)->toBe((string) $checkedInStatus->id);
});

test('invalid state machine transition on drag is rejected with 422 and friendly error message', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
    ]);

    // Invalid transition: PENDING directly to COMPLETED (PRD 213.1)
    $response = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'COMPLETED',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Status booking tidak dapat diubah ke tahap tersebut.');
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::PENDING);
});

test('payment-required guard: PENDING -> CONFIRMED with unpaid booking and payment requirement is blocked (PRD 24)', function () {
    $env = $this->createTenantEnvironment('Badminton Arena');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'price_idr' => 100000,
    ]);

    // Booking has deposit_idr > 0 and payment_status UNPAID
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'payment_status' => 'UNPAID',
        'deposit_idr' => 50000,
        'hold_expires_at' => now()->addMinutes(15),
    ]);

    // Attempt drag: PENDING -> CONFIRMED
    $response = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'CONFIRMED',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment([
        'message' => "Tidak boleh dipindahkan sebelum payment valid. Booking {$booking->code} memerlukan pembayaran.",
    ]);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::PENDING);

    // Now mark booking as PAID
    $booking->update(['payment_status' => 'PAID']);

    $validResponse = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'CONFIRMED',
    ]);
    $validResponse->assertStatus(200);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CONFIRMED);
});

test('payment-required guard can be explicitly bypassed when owner overrides at desk', function () {
    $env = $this->createTenantEnvironment('Studio Music');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'payment_status' => 'UNPAID',
        'deposit_idr' => 75000,
        'hold_expires_at' => now()->addMinutes(15),
    ]);

    // Override with bypass_payment_guard
    $response = $this->actingAs($owner)->postJson(route('owner.bookings.status', $booking->id), [
        'status' => 'CONFIRMED',
        'bypass_payment_guard' => true,
        'reason' => 'Customer bayar tunai di tempat.',
    ]);

    $response->assertStatus(200);
    expect($booking->fresh()->status_category)->toBe(BookingStatusCategory::CONFIRMED);
});

test('terminal statuses cannot transition further on kanban drag (PRD 213.2)', function () {
    $env = $this->createTenantEnvironment('Rental Car');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $completedBooking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::COMPLETED,
    ]);

    $response = $this->actingAs($owner)->postJson(route('owner.bookings.status', $completedBooking->id), [
        'status' => 'IN_PROGRESS',
    ]);

    $response->assertStatus(422);
    expect($completedBooking->fresh()->status_category)->toBe(BookingStatusCategory::COMPLETED);
});
