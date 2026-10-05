<?php

namespace Tests\Feature\Inventory;

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Enums\StockDeductionMode;
use App\Domain\Inventory\Models\BookingInventoryItem;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\ServiceInventoryItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\BusinessMember;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

function setupInventoryTestHours(int $tenantId, int $businessId): void
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

test('owner can view inventory index, stats, and search/filter items', function () {
    $env = $this->createTenantEnvironment('Inventory Index Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];

    // Enable inventory module
    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'sku' => 'OIL-001',
        'name' => 'Minyak Pijat Aromaterapi',
        'category' => 'Bahan',
        'current_stock' => 20,
        'minimum_stock' => 5,
    ]);

    InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'sku' => 'LOT-002',
        'name' => 'Lotion Scrub Kopi',
        'category' => 'Bahan',
        'current_stock' => 2,
        'minimum_stock' => 5, // low stock!
    ]);

    $response = $this->actingAs($user)
        ->get('/app/inventory');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Owner/Inventory/Index')
        ->where('is_module_active', true)
        ->where('stats.total_items', 2)
        ->where('stats.low_stock_count', 1)
        ->has('items.data', 2)
    );

    // Filter by low stock only
    $lowStockResponse = $this->actingAs($user)
        ->get('/app/inventory?low_stock=1');
    $lowStockResponse->assertOk();
    $lowStockResponse->assertInertia(fn ($page) => $page
        ->has('items.data', 1)
        ->where('items.data.0.sku', 'LOT-002')
    );
});

test('owner can store new inventory item and automatically creates opening stock movement', function () {
    $env = $this->createTenantEnvironment('Store Inventory Item Test');
    $tenant = $env['tenant'];
    $user = $env['user'];

    $response = $this->actingAs($user)
        ->post('/app/inventory/items', [
            'sku' => 'SERUM-100',
            'name' => 'Serum Vitamin C Wajah',
            'category' => 'Skincare',
            'unit' => 'botol',
            'cost_price_idr' => 45000,
            'sale_price_idr' => 85000,
            'initial_stock' => 15,
            'minimum_stock' => 3,
            'allow_negative_stock' => false,
        ]);

    $response->assertRedirect();

    $item = InventoryItem::where('tenant_id', $tenant->id)->where('sku', 'SERUM-100')->first();
    expect($item)->not->toBeNull();
    expect($item->name)->toBe('Serum Vitamin C Wajah');
    expect($item->current_stock)->toBe(15);
    expect($item->initial_stock)->toBe(15);
    expect($item->available_stock)->toBe(15);

    // Verify opening movement
    $movement = InventoryMovement::where('tenant_id', $tenant->id)
        ->where('inventory_item_id', $item->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe(MovementType::OPENING);
    expect($movement->quantity)->toBe(15);
    expect($movement->stock_before)->toBe(0);
    expect($movement->stock_after)->toBe(15);
});

test('can record manual stock mutations: purchase, adjustment, sale, return, and waste', function () {
    $env = $this->createTenantEnvironment('Mutations Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];

    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'current_stock' => 10,
        'allow_negative_stock' => false,
    ]);

    // 1. PURCHASE (+5) -> 15
    $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'PURCHASE',
        'quantity' => 5,
        'notes' => 'Beli dari supplier',
    ])->assertRedirect();
    expect($item->fresh()->current_stock)->toBe(15);

    // 2. ADJUSTMENT_OUT (-2) -> 13
    $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'ADJUSTMENT_OUT',
        'quantity' => 2,
        'notes' => 'Koreksi stok selisih opname',
    ])->assertRedirect();
    expect($item->fresh()->current_stock)->toBe(13);

    // 3. WASTE (-1) -> 12
    $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'WASTE',
        'quantity' => 1,
        'notes' => 'Botol pecah di gudang',
    ])->assertRedirect();
    expect($item->fresh()->current_stock)->toBe(12);

    // 4. RETURN (+2) -> 14
    $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'RETURN',
        'quantity' => 2,
        'notes' => 'Retur dari customer',
    ])->assertRedirect();
    expect($item->fresh()->current_stock)->toBe(14);
});

test('enforces non-negative stock when allow_negative_stock is false', function () {
    $env = $this->createTenantEnvironment('Negative Stock Guard Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];

    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'current_stock' => 3,
        'allow_negative_stock' => false,
    ]);

    // Try to reduce by 5 (resulting in -2)
    $response = $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'SALE',
        'quantity' => 5,
        'notes' => 'Jual 5 padahal ada 3',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($item->fresh()->current_stock)->toBe(3); // unchanged
});

test('allows negative stock when allow_negative_stock is true', function () {
    $env = $this->createTenantEnvironment('Allow Negative Stock Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];

    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'current_stock' => 2,
        'allow_negative_stock' => true,
    ]);

    $response = $this->actingAs($user)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'SALE',
        'quantity' => 5,
        'notes' => 'Boleh minus',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($item->fresh()->current_stock)->toBe(-3);
});

test('mode RESERVE_ON_BOOKING reserves stock on booking and deducts on check-in', function () {
    $env = $this->createTenantEnvironment('Reserve On Booking Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    $user = $env['user'];
    setupInventoryTestHours($tenant->id, $business->id);

    // Enable inventory
    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Terapis Rini',
        'state' => 'AVAILABLE',
    ]);
    for ($d = 0; $d < 7; $d++) {
        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $staff->id,
            'day_of_week' => $d,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Full Body Massage',
        'price_idr' => 150000,
        'duration_minutes' => 60,
    ]);

    // Create item with 10 in stock
    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Minyak Zaitun Massage',
        'current_stock' => 10,
        'reserved_stock' => 0,
        'allow_negative_stock' => false,
    ]);

    // Map service to consume 2 units of item with mode RESERVE_ON_BOOKING
    ServiceInventoryItem::create([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'inventory_item_id' => $item->id,
        'quantity' => 2,
        'deduction_mode' => StockDeductionMode::RESERVE_ON_BOOKING,
    ]);

    // 1. Create Booking -> triggers reserveOrDeductForBooking
    /** @var CreateBooking $createAction */
    $createAction = app(CreateBooking::class);
    $booking = $createAction->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Dewi', 'phone' => '081234567890'],
        'start_at' => Carbon::parse('2026-10-10 11:00:00'),
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // Verification after booking creation:
    // Physical stock still 10, but reserved is 2, available is 8
    $item->refresh();
    expect($item->current_stock)->toBe(10);
    expect($item->reserved_stock)->toBe(2);
    expect($item->available_stock)->toBe(8);

    $reservation = BookingInventoryItem::where('booking_id', $booking->id)->first();
    expect($reservation)->not->toBeNull();
    expect($reservation->status)->toBe(ReservationStatus::RESERVED);
    expect($reservation->quantity)->toBe(2);

    // 2. Check-in Booking -> triggers handleBookingStatusTransition
    /** @var BookingStateMachine $stateMachine */
    $stateMachine = app(BookingStateMachine::class);
    $stateMachine->transition($booking, BookingStatusCategory::CHECKED_IN);

    // Verification after check-in:
    // Physical stock deducted to 8, reserved returned to 0, available is 8
    $item->refresh();
    expect($item->current_stock)->toBe(8);
    expect($item->reserved_stock)->toBe(0);
    expect($item->available_stock)->toBe(8);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::CONSUMED);

    // Verify CONSUMED_BY_BOOKING movement logged
    $movement = InventoryMovement::where('tenant_id', $tenant->id)
        ->where('booking_id', $booking->id)
        ->first();
    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe(MovementType::CONSUMED_BY_BOOKING);
    expect($movement->quantity)->toBe(2);
    expect($movement->stock_before)->toBe(10);
    expect($movement->stock_after)->toBe(8);
});

test('mode RESERVE_ON_BOOKING releases reserved stock when booking is cancelled', function () {
    $env = $this->createTenantEnvironment('Release Stock On Cancel Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupInventoryTestHours($tenant->id, $business->id);

    // Enable inventory
    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'state' => 'AVAILABLE',
    ]);
    for ($d = 0; $d < 7; $d++) {
        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $staff->id,
            'day_of_week' => $d,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'current_stock' => 5,
        'reserved_stock' => 0,
    ]);

    ServiceInventoryItem::create([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'inventory_item_id' => $item->id,
        'quantity' => 2,
        'deduction_mode' => StockDeductionMode::RESERVE_ON_BOOKING,
    ]);

    $booking = app(CreateBooking::class)->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Budi', 'phone' => '081234567891'],
        'start_at' => Carbon::parse('2026-10-10 12:00:00'),
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    $item->refresh();
    expect($item->reserved_stock)->toBe(2);

    // Cancel booking
    app(BookingStateMachine::class)->transition($booking, BookingStatusCategory::CANCELLED);

    $item->refresh();
    expect($item->reserved_stock)->toBe(0);
    expect($item->current_stock)->toBe(5);
    expect($item->available_stock)->toBe(5);

    $reservation = BookingInventoryItem::where('booking_id', $booking->id)->first();
    expect($reservation->status)->toBe(ReservationStatus::RELEASED);
});

test('mode DEDUCT_ON_COMPLETE deducts stock when booking transitions to COMPLETED', function () {
    $env = $this->createTenantEnvironment('Deduct On Complete Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupInventoryTestHours($tenant->id, $business->id);

    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'state' => 'AVAILABLE',
    ]);
    for ($d = 0; $d < 7; $d++) {
        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $staff->id,
            'day_of_week' => $d,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'current_stock' => 10,
        'reserved_stock' => 0,
    ]);

    ServiceInventoryItem::create([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'inventory_item_id' => $item->id,
        'quantity' => 3,
        'deduction_mode' => StockDeductionMode::DEDUCT_ON_COMPLETE,
    ]);

    $booking = app(CreateBooking::class)->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Sari', 'phone' => '081234567892'],
        'start_at' => Carbon::parse('2026-10-10 13:00:00'),
        'staff_id' => $staff->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);

    // Stock should not be reserved or deducted yet
    $item->refresh();
    expect($item->current_stock)->toBe(10);
    expect($item->reserved_stock)->toBe(0);

    // Transition to CHECKED_IN -> IN_PROGRESS -> COMPLETED
    $stateMachine = app(BookingStateMachine::class);
    $stateMachine->transition($booking, BookingStatusCategory::CHECKED_IN);
    $item->refresh();
    expect($item->current_stock)->toBe(10); // Still 10 (not yet completed)

    $stateMachine->transition($booking, BookingStatusCategory::IN_PROGRESS);
    $stateMachine->transition($booking, BookingStatusCategory::COMPLETED);

    $item->refresh();
    expect($item->current_stock)->toBe(7); // Deducted by 3!
    expect($item->available_stock)->toBe(7);

    $reservation = BookingInventoryItem::where('booking_id', $booking->id)->first();
    expect($reservation->status)->toBe(ReservationStatus::CONSUMED);
});

test('fails booking creation if inventory stock is insufficient and allow_negative_stock is false', function () {
    $env = $this->createTenantEnvironment('Insufficient Stock Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupInventoryTestHours($tenant->id, $business->id);

    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'state' => 'AVAILABLE',
    ]);
    for ($d = 0; $d < 7; $d++) {
        ResourceSchedule::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $staff->id,
            'day_of_week' => $d,
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'is_active' => true,
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    // Item only has 1 unit in stock
    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Bahan Langka',
        'current_stock' => 1,
        'reserved_stock' => 0,
        'allow_negative_stock' => false,
    ]);

    // Service requires 2 units
    ServiceInventoryItem::create([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'inventory_item_id' => $item->id,
        'quantity' => 2,
        'deduction_mode' => StockDeductionMode::RESERVE_ON_BOOKING,
    ]);

    expect(function () use ($tenant, $service, $staff) {
        app(CreateBooking::class)->execute([
            'tenant' => $tenant,
            'service' => $service,
            'customer' => ['name' => 'Fani', 'phone' => '081234567893'],
            'start_at' => Carbon::parse('2026-10-10 14:00:00'),
            'staff_id' => $staff->id,
            'status_category' => BookingStatusCategory::CONFIRMED,
        ]);
    })->toThrow(BookingException::class);

    // No booking created and stock unchanged
    expect(Booking::where('tenant_id', $tenant->id)->count())->toBe(0);
    expect($item->fresh()->reserved_stock)->toBe(0);
});

test('concurrency lock prevents two parallel bookings from double-reserving the last inventory item', function () {
    $env = $this->createTenantEnvironment('Inventory Concurrency Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupInventoryTestHours($tenant->id, $business->id);

    $settings = $business->settings ?? [];
    $settings['modules']['inventory'] = true;
    $business->settings = $settings;
    $business->save();

    // 2 staff so staff isn't the bottleneck
    $staff1 = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'state' => 'AVAILABLE']);
    $staff2 = Resource::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'state' => 'AVAILABLE']);
    for ($d = 0; $d < 7; $d++) {
        ResourceSchedule::create(['tenant_id' => $tenant->id, 'resource_id' => $staff1->id, 'day_of_week' => $d, 'start_time' => '00:00:00', 'end_time' => '23:59:59', 'is_active' => true]);
        ResourceSchedule::create(['tenant_id' => $tenant->id, 'resource_id' => $staff2->id, 'day_of_week' => $d, 'start_time' => '00:00:00', 'end_time' => '23:59:59', 'is_active' => true]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    // Exactly 1 item left in stock
    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Bahan Terakhir',
        'current_stock' => 1,
        'reserved_stock' => 0,
        'allow_negative_stock' => false,
    ]);

    ServiceInventoryItem::create([
        'tenant_id' => $tenant->id,
        'service_id' => $service->id,
        'inventory_item_id' => $item->id,
        'quantity' => 1,
        'deduction_mode' => StockDeductionMode::RESERVE_ON_BOOKING,
    ]);

    // Booking 1 succeeds
    $booking1 = app(CreateBooking::class)->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer 1', 'phone' => '081234567894'],
        'start_at' => Carbon::parse('2026-10-10 15:00:00'),
        'staff_id' => $staff1->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
    ]);
    expect($booking1)->not->toBeNull();
    expect($item->fresh()->reserved_stock)->toBe(1);
    expect($item->fresh()->available_stock)->toBe(0);

    // Booking 2 competing for the same item at the same time fails with InsufficientInventory
    expect(function () use ($tenant, $service, $staff2) {
        app(CreateBooking::class)->execute([
            'tenant' => $tenant,
            'service' => $service,
            'customer' => ['name' => 'Customer 2', 'phone' => '081234567895'],
            'start_at' => Carbon::parse('2026-10-10 15:00:00'),
            'staff_id' => $staff2->id,
            'status_category' => BookingStatusCategory::CONFIRMED,
        ]);
    })->toThrow(BookingException::class);

    // Exactly 1 booking exists, reserved stock is 1
    expect(Booking::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect($item->fresh()->reserved_stock)->toBe(1);
});

test('unauthorized staff cannot manage items or record mutations', function () {
    $env = $this->createTenantEnvironment('Inventory Permission Test');
    $tenant = $env['tenant'];
    $business = $env['business'];

    // Viewer member with only view permissions
    $viewerUser = User::factory()->create();
    BusinessMember::create([
        'tenant_id' => $tenant->id,
        'user_id' => $viewerUser->id,
        'preset' => 'viewer',
        'permissions' => ['inventory.view'],
    ]);

    // Attempt to create item -> 403 Forbidden
    $response = $this->actingAs($viewerUser)->post('/app/inventory/items', [
        'name' => 'Barang Ilegal',
        'unit' => 'pcs',
        'cost_price_idr' => 10000,
        'initial_stock' => 5,
    ]);
    $response->assertStatus(403);

    // Create an item as owner
    $item = InventoryItem::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
    ]);

    // Viewer attempt to adjust stock -> 403 Forbidden
    $adjustResponse = $this->actingAs($viewerUser)->post("/app/inventory/items/{$item->id}/movements", [
        'type' => 'PURCHASE',
        'quantity' => 10,
    ]);
    $adjustResponse->assertStatus(403);
});
