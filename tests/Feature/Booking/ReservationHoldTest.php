<?php

namespace Tests\Feature\Booking;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Service\Models\Service;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
    Carbon::setTestNow('2026-10-10 10:00:00'); // Saturday
});

afterEach(function () {
    Carbon::setTestNow();
    TenantContext::clear();
});

function setupTestHours(int $tenantId, int $businessId): void
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

test('booking created with requires_payment starts as PENDING with 10-minute hold_expires_at by default', function () {
    $env = $this->createTenantEnvironment('Barber Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'John Barber',
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Haircut',
        'price_idr' => 75000,
        'duration_minutes' => 60,
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => [
            'name' => 'Faqih Test',
            'phone' => '08123456789',
        ],
        'start_at' => '2026-10-12 14:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
    ]);

    expect($booking->status_category)->toBe(BookingStatusCategory::PENDING)
        ->and($booking->hold_expires_at)->not->toBeNull()
        ->and($booking->hold_expires_at->toDateTimeString())->toBe('2026-10-10 10:10:00')
        ->and($booking->isHoldActive())->toBeTrue()
        ->and($booking->isHoldExpired())->toBeFalse()
        ->and($booking->getHoldRemainingSeconds())->toBe(600);
});

test('booking created respects custom hold duration from business settings or input', function () {
    $env = $this->createTenantEnvironment('Spa Test');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $business->settings = [
        'payment_gateway' => [
            'hold_duration_minutes' => 20,
        ],
    ];
    $business->save();

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'duration_minutes' => 60,
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Jane', 'phone' => '08198765432'],
        'start_at' => '2026-10-12 14:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
    ]);

    // Expected 20 minutes from now (10:00 -> 10:20)
    expect($booking->hold_expires_at->toDateTimeString())->toBe('2026-10-10 10:20:00')
        ->and($booking->getHoldRemainingSeconds())->toBe(1200);

    // Override explicitly with hold_minutes in input
    $booking2 = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Jane 2', 'phone' => '08198765433'],
        'start_at' => '2026-10-12 16:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_minutes' => 45,
    ]);

    expect($booking2->hold_expires_at->toDateTimeString())->toBe('2026-10-10 10:45:00');
});

test('active reservation hold blocks slot in AvailabilityService', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1, // Monday (2026-10-12 is Monday)
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

    // Create a PENDING booking with active hold for Monday at 10:00
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer Active Hold', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 10:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_minutes' => 15,
    ]);

    expect($booking->isHoldActive())->toBeTrue();

    /** @var AvailabilityService $availabilityService */
    $availabilityService = app(AvailabilityService::class);
    $slots = $availabilityService->getSlotsForDate($tenant, $service, '2026-10-12', [
        'staff_id' => $staff->id,
    ]);

    // Check slot at 10:00
    $slot10 = $slots->firstWhere('start_time', '10:00');
    expect($slot10)->not->toBeNull()
        ->and($slot10['is_available'])->toBeFalse();
});

test('expired hold does not block slot in AvailabilityService even before background command runs', function () {
    $env = $this->createTenantEnvironment('Court Rental');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1, // Monday
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

    // Create a PENDING booking with hold that expired 5 minutes ago
    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Expired Customer', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 10:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_expires_at' => Carbon::now()->subMinutes(5),
    ]);

    expect($booking->isHoldExpired())->toBeTrue();

    /** @var AvailabilityService $availabilityService */
    $availabilityService = app(AvailabilityService::class);
    $slots = $availabilityService->getSlotsForDate($tenant, $service, '2026-10-12', [
        'staff_id' => $staff->id,
    ]);

    // The 10:00 slot must be available because the hold has expired
    $slot10 = $slots->firstWhere('start_time', '10:00');
    expect($slot10)->not->toBeNull()
        ->and($slot10['is_available'])->toBeTrue();

    // Another customer can book that exact slot successfully
    $booking2 = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'New Customer', 'phone' => '08129999888'],
        'start_at' => '2026-10-12 10:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => false,
    ]);

    expect($booking2->status_category)->toBe(BookingStatusCategory::CONFIRMED);
});

test('bookings:expire-holds command transitions expired holds to EXPIRED and releases allocations', function () {
    $env = $this->createTenantEnvironment('Music Studio');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'duration_minutes' => 60,
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    // 1. Expired booking
    $expiredBooking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Expired User', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 10:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_expires_at' => Carbon::now()->subMinutes(1),
    ]);

    // 2. Active hold booking
    $activeBooking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Active User', 'phone' => '08123456780'],
        'start_at' => '2026-10-12 11:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_expires_at' => Carbon::now()->addMinutes(10),
    ]);

    // Run the artisan scheduler command
    Artisan::call('bookings:expire-holds');
    $output = Artisan::output();

    expect($output)->toContain('1 booking');

    $expiredBooking->refresh();
    $activeBooking->refresh();

    // Expired booking is transitioned to EXPIRED and allocations RELEASED
    expect($expiredBooking->status_category)->toBe(BookingStatusCategory::EXPIRED)
        ->and($expiredBooking->allocations()->first()->status)->toBe(AllocationStatus::RELEASED);

    // Active booking remains PENDING and allocations ACTIVE
    expect($activeBooking->status_category)->toBe(BookingStatusCategory::PENDING)
        ->and($activeBooking->allocations()->first()->status)->toBe(AllocationStatus::ACTIVE);
});

test('payment completed within hold window transitions booking to CONFIRMED', function () {
    $env = $this->createTenantEnvironment('Photo Studio');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'price_idr' => 200000,
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Customer Paying', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 14:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_minutes' => 15,
    ]);

    expect($booking->status_category)->toBe(BookingStatusCategory::PENDING)
        ->and($booking->isHoldActive())->toBeTrue();

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking, Invoice::MODEL_FULL_PAYMENT);

    // Process manual payment within active hold
    $paymentService->recordManualPayment($invoice, 200000, Payment::METHOD_CASH, 'Paid at venue');

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED)
        ->and($booking->payment_status)->toBe('PAID')
        ->and($booking->allocations()->first()->status)->toBe(AllocationStatus::ACTIVE);
});

test('customer payBooking endpoint rejects expired hold with friendly HOLD_EXPIRED message', function () {
    $env = $this->createTenantEnvironment('Beauty Bar');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'price_idr' => 150000,
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Late Customer', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 14:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_expires_at' => Carbon::now()->subMinutes(10), // expired 10 minutes ago
    ]);

    $token = $booking->raw_manage_token;

    $response = $this->postJson("/{$business->slug}/booking/manage/{$token}/pay");

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error_code' => 'HOLD_EXPIRED',
        ]);
});

test('race condition protection: payment arriving for already EXPIRED booking is recorded in ledger without crashing', function () {
    $env = $this->createTenantEnvironment('Fitness Center');
    $tenant = $env['tenant'];
    $business = $env['business'];
    setupTestHours($tenant->id, $business->id);

    $business->settings = [
        'payment_gateway' => [
            'midtrans_server_key' => 'SB-Mid-server-race-test',
            'default_provider' => 'midtrans',
            'is_production' => false,
        ],
    ];
    $business->save();

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'price_idr' => 100000,
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    /** @var CreateBooking $createBooking */
    $createBooking = app(CreateBooking::class);

    $booking = $createBooking->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Race Customer', 'phone' => '08123456789'],
        'start_at' => '2026-10-12 14:00:00',
        'staff_id' => $staff->id,
        'requires_payment' => true,
        'hold_expires_at' => Carbon::now()->subMinutes(2),
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking, Invoice::MODEL_FULL_PAYMENT);

    // 1. Expire job runs first
    Artisan::call('bookings:expire-holds');

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::EXPIRED);

    // 2. Webhook arrives now for the expired booking
    $orderId = $invoice->invoice_number;
    $grossAmount = '100000.00';
    $statusCode = '200';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . 'SB-Mid-server-race-test');

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
        'transaction_id' => 'midtrans-race-trx-001',
        'signature_key' => $signature,
        'transaction_time' => Carbon::now()->toDateTimeString(),
    ];

    $response = $this->postJson('/webhooks/payment/midtrans', $payload);

    $response->assertStatus(200);

    // Payment must be stored in DB and marked settlement
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'provider_transaction_id' => 'midtrans-race-trx-001',
        'status' => Payment::STATUS_SETTLEMENT,
    ]);

    // Booking remains EXPIRED (state machine does not perform invalid transition from terminal EXPIRED)
    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::EXPIRED);
});
