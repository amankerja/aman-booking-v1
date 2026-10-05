<?php

namespace Tests\Feature\Payment;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\PaymentRefund;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Service\Models\Service;
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

test('midtrans webhook with valid signature processes payment and transitions pending booking to confirmed', function () {
    $env = $this->createTenantEnvironment('Barbershop Premium');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $serverKey = 'SB-Mid-server-test-key-999';
    $business->settings = [
        'payment_gateway' => [
            'midtrans_server_key' => $serverKey,
            'default_provider' => 'midtrans',
            'is_production' => false,
        ],
    ];
    $business->save();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Signature Haircut',
        'price_idr' => 150000,
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'total_idr' => 150000,
        'payment_status' => 'UNPAID',
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking, Invoice::MODEL_FULL_PAYMENT);

    $orderId = $invoice->invoice_number;
    $statusCode = '200';
    $grossAmount = '150000.00';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
        'transaction_id' => 'mid-trx-001',
        'settlement_time' => now()->toDateTimeString(),
    ];

    $response = $this->postJson('/webhooks/payment/midtrans', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->amount_paid_idr)->toBe(150000);

    $booking->refresh();
    expect($booking->payment_status)->toBe('PAID')
        ->and($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED);

    $payment = Payment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe('midtrans')
        ->and($payment->payment_method)->toBe('qris')
        ->and($payment->status)->toBe(Payment::STATUS_SETTLEMENT)
        ->and($payment->provider_transaction_id)->toBe('mid-trx-001')
        ->and($payment->provider_event_id)->toBe('mid-trx-001_settlement');
});

test('midtrans webhook with invalid signature is rejected with 401', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $business = $env['business'];

    $business->settings = [
        'payment_gateway' => [
            'midtrans_server_key' => 'correct-secret-key',
        ],
    ];
    $business->save();

    $payload = [
        'order_id' => 'INV-2026-FAKE',
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => 'invalid_forged_signature_hash',
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
        'transaction_id' => 'mid-fake-001',
    ];

    $response = $this->postJson('/webhooks/payment/midtrans', $payload);

    $response->assertStatus(401)
        ->assertJson([
            'status' => 'error',
        ]);
});

test('xendit webhook with valid callback token processes invoice settlement', function () {
    $env = $this->createTenantEnvironment('Futsal Arena');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $xenditToken = 'xendit_webhook_token_secret_12345';
    $business->settings = [
        'payment_gateway' => [
            'xendit_webhook_token' => $xenditToken,
            'default_provider' => 'xendit',
        ],
    ];
    $business->save();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'price_idr' => 200000,
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'total_idr' => 200000,
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking, Invoice::MODEL_FULL_PAYMENT);

    $payload = [
        'id' => 'xendit-inv-abc-999',
        'external_id' => $invoice->invoice_number,
        'status' => 'PAID',
        'paid_amount' => 200000,
        'payment_method' => 'BANK_TRANSFER',
        'paid_at' => now()->toIso8601String(),
    ];

    $response = $this->withHeader('x-callback-token', $xenditToken)
        ->postJson('/webhooks/payment/xendit', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->amount_paid_idr)->toBe(200000);

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED);
});

test('xendit webhook with invalid callback token returns 401', function () {
    $env = $this->createTenantEnvironment('Yoga Studio');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $business->settings = [
        'payment_gateway' => [
            'xendit_webhook_token' => 'real-token',
        ],
    ];
    $business->save();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
    ]);
    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking);

    $payload = [
        'id' => 'xendit-fake',
        'external_id' => $invoice->invoice_number,
        'status' => 'PAID',
    ];

    $response = $this->withHeader('x-callback-token', 'wrong-token')
        ->postJson('/webhooks/payment/xendit', $payload);

    $response->assertStatus(401);
});

test('idempotency prevents duplicate processing of replayed webhook event', function () {
    $env = $this->createTenantEnvironment('Music Studio');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $serverKey = 'midtrans_secret_key';
    $business->settings = [
        'payment_gateway' => [
            'midtrans_server_key' => $serverKey,
        ],
    ];
    $business->save();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'price_idr' => 100000]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'total_idr' => 100000,
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking);

    $orderId = $invoice->invoice_number;
    $statusCode = '200';
    $grossAmount = '100000.00';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
        'transaction_id' => 'mid-idempotent-event-1',
    ];

    // 1st delivery
    $firstResponse = $this->postJson('/webhooks/payment/midtrans', $payload);
    $firstResponse->assertStatus(200)->assertJson(['status' => 'success']);

    $invoice->refresh();
    expect($invoice->amount_paid_idr)->toBe(100000);
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);

    // 2nd delivery (Replay / Duplicate)
    $secondResponse = $this->postJson('/webhooks/payment/midtrans', $payload);
    $secondResponse->assertStatus(200)->assertJson(['status' => 'duplicate']);

    // Ensure amount was not credited twice
    $invoice->refresh();
    expect($invoice->amount_paid_idr)->toBe(100000);
    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('out of order event protection prevents settled payment downgrade to pending', function () {
    $env = $this->createTenantEnvironment('Coworking Space');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $serverKey = 'midtrans_secret_key';
    $business->settings = [
        'payment_gateway' => [
            'midtrans_server_key' => $serverKey,
        ],
    ];
    $business->save();

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'price_idr' => 75000]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'total_idr' => 75000,
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking);

    // Already settled payment
    Payment::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'booking_id' => $booking->id,
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-SETTLED-01',
        'provider' => 'midtrans',
        'provider_event_id' => 'mid-settlement-first',
        'amount_idr' => 75000,
        'status' => Payment::STATUS_SETTLEMENT,
        'paid_at' => now(),
    ]);
    $invoice->update(['status' => Invoice::STATUS_PAID, 'amount_paid_idr' => 75000]);

    // Delayed pending event arrives with different event ID
    $orderId = $invoice->invoice_number;
    $statusCode = '201';
    $grossAmount = '75000.00';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'pending',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'mid-delayed-pending-event',
    ];

    $response = $this->postJson('/webhooks/payment/midtrans', $payload);

    $response->assertStatus(200)->assertJson(['status' => 'ignored']);

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID);
});

test('owner can record manual cash payment and booking transitions to confirmed', function () {
    $env = $this->createTenantEnvironment('Nail Art Salon');
    $owner = $env['user'];
    $tenant = $env['tenant'];
    $business = $env['business'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'price_idr' => 120000]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'total_idr' => 120000,
    ]);

    /** @var PaymentService $paymentService */
    $paymentService = app(PaymentService::class);
    $invoice = $paymentService->createInvoiceForBooking($booking);

    $response = $this->actingAs($owner)->post(route('owner.payments.manual'), [
        'invoice_id' => $invoice->id,
        'amount_idr' => 120000,
        'payment_method' => 'cash',
        'notes' => 'Dibayar tunai di meja kasir.',
    ]);

    $response->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->amount_paid_idr)->toBe(120000);

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED)
        ->and($booking->payment_status)->toBe('PAID');

    $payment = Payment::where('invoice_id', $invoice->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->provider)->toBe('manual')
        ->and($payment->payment_method)->toBe('cash');
});

test('refund requested by non-owner requires owner approval gate', function () {
    $env = $this->createTenantEnvironment('Dental Clinic Deluxe');
    $owner = $env['user'];
    $tenant = $env['tenant'];
    $business = $env['business'];

    // Create staff member
    $managerUser = User::factory()->create(['name' => 'Manager Staff']);
    \App\Domain\Tenant\Models\BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $managerUser->id,
        'preset' => 'STAFF',
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'price_idr' => 300000]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'total_idr' => 300000,
        'payment_status' => 'PAID',
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-REFUND-TEST-1',
        'payment_model' => Invoice::MODEL_FULL_PAYMENT,
        'amount_total_idr' => 300000,
        'amount_due_idr' => 300000,
        'amount_paid_idr' => 300000,
        'status' => Invoice::STATUS_PAID,
    ]);

    $payment = Payment::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'booking_id' => $booking->id,
        'invoice_id' => $invoice->id,
        'payment_number' => 'PAY-REFUND-TEST-1',
        'provider' => 'midtrans',
        'payment_method' => 'qris',
        'amount_idr' => 300000,
        'status' => Payment::STATUS_SETTLEMENT,
        'paid_at' => now(),
    ]);

    // 1. Manager requests refund
    $requestResponse = $this->actingAs($managerUser)->post(route('owner.payments.refunds.request'), [
        'payment_id' => $payment->id,
        'amount_idr' => 300000,
        'reason' => 'Pasien berhalangan hadir dan mengajukan refund sesuai kebijakan.',
    ]);
    $requestResponse->assertRedirect();

    $refund = PaymentRefund::where('payment_id', $payment->id)->first();
    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(PaymentRefund::STATUS_PENDING)
        ->and($refund->approved_by_user_id)->toBeNull();

    // 2. Non-owner cannot approve
    $unauthApprove = $this->actingAs($managerUser)->post(route('owner.payments.refunds.approve', ['id' => $refund->id]));
    $refund->refresh();
    expect($refund->status)->toBe(PaymentRefund::STATUS_PENDING);

    // 3. Owner approves refund
    $ownerApprove = $this->actingAs($owner)->post(route('owner.payments.refunds.approve', ['id' => $refund->id]));
    $ownerApprove->assertRedirect();

    $refund->refresh();
    expect($refund->status)->toBe(PaymentRefund::STATUS_APPROVED)
        ->and($refund->approved_by_user_id)->toBe($owner->id);

    $invoice->refresh();
    expect($invoice->amount_paid_idr)->toBe(0);

    $booking->refresh();
    expect($booking->payment_status)->toBe('REFUNDED');
});

test('owner can access payments dashboard and update settings', function () {
    $env = $this->createTenantEnvironment('Auto Detailing Workshop');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    // 1. Access payments index
    $indexResponse = $this->actingAs($owner)->get(route('owner.payments.index'));
    $indexResponse->assertStatus(200);

    // 2. Update payment gateway settings
    $settingsResponse = $this->actingAs($owner)->put(route('owner.settings.payment.update'), [
        'default_provider' => 'midtrans',
        'default_model' => 'deposit',
        'deposit_percentage' => 40,
        'is_production' => true,
        'midtrans_server_key' => 'Mid-server-LIVE-12345',
        'midtrans_client_key' => 'Mid-client-LIVE-67890',
        'xendit_secret_key' => null,
        'xendit_public_key' => null,
        'xendit_webhook_token' => null,
    ]);

    $settingsResponse->assertRedirect();

    $business = Business::where('tenant_id', $tenant->id)->first();
    $gw = $business->settings['payment_gateway'] ?? [];
    expect($gw['default_provider'])->toBe('midtrans')
        ->and($gw['default_model'])->toBe('deposit')
        ->and($gw['deposit_percentage'])->toBe(40)
        ->and($gw['is_production'])->toBeTrue()
        ->and($gw['midtrans_server_key'])->toBe('Mid-server-LIVE-12345');
});

test('customer can query payment session in booking manage portal', function () {
    $env = $this->createTenantEnvironment('Beauty Center');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'business_id' => $business->id, 'price_idr' => 250000]);
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'total_idr' => 250000,
        'manage_token' => hash('sha256', 'plain-customer-token-123'),
    ]);

    $response = $this->postJson("/{$business->slug}/booking/manage/plain-customer-token-123/pay");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'invoice_id',
            'invoice_number',
            'amount_due_idr',
            'provider',
        ]);
});
