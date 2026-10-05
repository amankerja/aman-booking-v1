<?php

namespace Tests\Feature\Customer;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Services\CustomerService;
use App\Domain\Notification\Models\NotificationLog;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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

test('marketing message cannot be sent without marketing consent (PRD 40)', function () {
    $env = $this->createTenantEnvironment('Klinik Estetika');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    /** @var Customer $customerWithoutConsent */
    $customerWithoutConsent = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Siti Rahma',
        'phone_e164' => '+6281234560001',
        'email' => 'siti@example.com',
        'marketing_consent_at' => null,
    ]);

    expect($customerWithoutConsent->canReceiveMarketing())->toBeFalse();

    /** @var CustomerService $service */
    $service = app(CustomerService::class);

    // Expect AuthorizationException when trying to send marketing message without consent
    expect(fn () => $service->sendMarketingMessage($customerWithoutConsent, 'Diskon 20% khusus hari ini!'))
        ->toThrow(AuthorizationException::class);

    // Verify no NotificationLog was created
    expect(NotificationLog::withoutGlobalScopes()->where('event', 'MARKETING_BROADCAST')->count())->toBe(0);
});

test('marketing message succeeds when customer has explicit marketing consent (PRD 40)', function () {
    $env = $this->createTenantEnvironment('Klinik Estetika');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    /** @var Customer $customerWithConsent */
    $customerWithConsent = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Dewi Lestari',
        'phone_e164' => '+6281234560002',
        'email' => 'dewi@example.com',
        'marketing_consent_at' => now(),
    ]);

    expect($customerWithConsent->canReceiveMarketing())->toBeTrue();

    /** @var CustomerService $service */
    $service = app(CustomerService::class);
    $sent = $service->sendMarketingMessage($customerWithConsent, 'Promo Anniversary: Diskon 30%!', 'WHATSAPP');

    expect($sent)->toBeTrue();

    $log = NotificationLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('event', 'MARKETING_BROADCAST')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->recipient)->toBe('+6281234560002')
        ->and($log->body)->toContain('Promo Anniversary');
});

test('customer data anonymization redacts PII while keeping booking and financial records intact (PRD 54)', function () {
    $env = $this->createTenantEnvironment('Dental Smile');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $env['business']->id,
        'name' => 'Scaling Gigi',
        'duration_minutes' => 45,
        'price_idr' => 250000,
        'pricing_type' => 'FIXED',
    ]);

    /** @var Customer $customer */
    $customer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Ferry Maryadi',
        'phone_e164' => '+6281987654321',
        'email' => 'ferry@example.com',
        'notes' => 'Catatan alergi obat penisilin.',
        'tags' => ['vip'],
        'marketing_consent_at' => now(),
    ]);

    // Create a booking linked to this customer
    $booking = Booking::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'service_snapshot' => ['name' => 'Scaling Gigi', 'price_idr' => 250000],
        'code' => 'BK-FERRY-01',
        'status_category' => BookingStatusCategory::COMPLETED,
        'start_at' => now()->subDays(5),
        'end_at' => now()->subDays(5)->addMinutes(45),
        'total_idr' => 250000,
        'paid_amount_idr' => 250000,
        'payment_status' => 'PAID',
    ]);

    // Anonymize via endpoint
    $response = $this->actingAs($owner)->post(route('owner.customers.anonymize', $customer->id), [
        'reason' => 'Hak untuk dilupakan sesuai UU PDP',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $customer->refresh();

    // Verify PII is redacted
    expect($customer->is_anonymized)->toBeTrue()
        ->and($customer->name)->toContain('Pelanggan Anonim #'.$customer->id)
        ->and($customer->email)->toBeNull()
        ->and($customer->notes)->toBeNull()
        ->and($customer->phone_e164)->not->toBe('+6281987654321')
        ->and($customer->phone_e164)->toStartWith('+6200')
        ->and($customer->tags)->toBe(['anonymized'])
        ->and($customer->marketing_consent_at)->toBeNull()
        ->and($customer->canReceiveMarketing())->toBeFalse();

    // Verify booking is still intact and linked to customer
    $booking->refresh();
    expect($booking->customer_id)->toBe($customer->id)
        ->and($booking->total_idr)->toBe(250000)
        ->and($booking->status_category)->toBe(BookingStatusCategory::COMPLETED);
});

test('internal notes can be appended with timestamp and staff attribution (PRD 39)', function () {
    $env = $this->createTenantEnvironment('Salon Cantik');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    /** @var Customer $customer */
    $customer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Maya Angela',
        'phone_e164' => '+6281233334444',
        'notes' => 'Catatan awal dari onboarding.',
    ]);

    $response = $this->actingAs($owner)->post(route('owner.customers.notes.store', $customer->id), [
        'note' => 'Pelanggan lebih menyukai terapis Mbak Dian.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $customer->refresh();
    expect($customer->notes)->toContain('Catatan awal dari onboarding.')
        ->and($customer->notes)->toContain($owner->name)
        ->and($customer->notes)->toContain('Pelanggan lebih menyukai terapis Mbak Dian.');
});

test('preset segmentation filter correctly filters customer cohorts', function () {
    $env = $this->createTenantEnvironment('Fitness Hub');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $env['business']->id,
        'name' => 'Fitness Session',
        'duration_minutes' => 60,
        'price_idr' => 100000,
        'pricing_type' => 'FIXED',
    ]);

    // 1. VIP
    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'VIP Customer',
        'phone_e164' => '+628100000001',
        'tags' => ['vip', 'gold'],
    ]);

    // 2. Repeat Customer (2 bookings)
    $repeatCustomer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Repeat Customer',
        'phone_e164' => '+628100000002',
    ]);
    for ($i = 0; $i < 2; $i++) {
        Booking::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $repeatCustomer->id,
            'service_id' => $service->id,
            'service_snapshot' => ['name' => 'Fitness Session', 'price_idr' => 100000],
            'code' => 'BK-REP-'.$i,
            'status_category' => BookingStatusCategory::COMPLETED,
            'start_at' => now()->subDays($i + 1),
            'end_at' => now()->subDays($i + 1)->addHour(),
            'total_idr' => 100000,
        ]);
    }

    // 3. No show risk
    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Risky Customer',
        'phone_e164' => '+628100000003',
        'no_show_count' => 3,
    ]);

    // 4. With consent
    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Consenting Customer',
        'phone_e164' => '+628100000004',
        'marketing_consent_at' => now(),
    ]);

    // 5. Anonymized
    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Pelanggan Anonim #99',
        'phone_e164' => '+620000000099',
        'anonymized_at' => now(),
    ]);

    // Test VIP segment query
    $vipCustomers = Customer::where('tenant_id', $tenant->id)->withSegment('VIP')->get();
    expect($vipCustomers->pluck('name')->all())->toContain('VIP Customer');

    // Test REPEAT segment query
    $repeatCustomers = Customer::where('tenant_id', $tenant->id)->withSegment('REPEAT')->get();
    expect($repeatCustomers->pluck('name')->all())->toContain('Repeat Customer');

    // Test NO_SHOW_RISK segment query
    $riskyCustomers = Customer::where('tenant_id', $tenant->id)->withSegment('NO_SHOW_RISK')->get();
    expect($riskyCustomers->pluck('name')->all())->toContain('Risky Customer');

    // Test WITH_CONSENT segment query
    $consentCustomers = Customer::where('tenant_id', $tenant->id)->withSegment('WITH_CONSENT')->get();
    expect($consentCustomers->pluck('name')->all())->toContain('Consenting Customer');

    // Test ANONYMIZED segment query
    $anonCustomers = Customer::where('tenant_id', $tenant->id)->withSegment('ANONYMIZED')->get();
    expect($anonCustomers->pluck('name')->all())->toContain('Pelanggan Anonim #99');
});

test('customer profile detail endpoint returns enriched stats and booking history (PRD 39)', function () {
    $env = $this->createTenantEnvironment('Wellness Spa');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $env['business']->id,
        'name' => 'Massage Body',
        'duration_minutes' => 60,
        'price_idr' => 350000,
        'pricing_type' => 'FIXED',
    ]);

    $customer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Indah Permata',
        'phone_e164' => '+628123445566',
        'no_show_count' => 1,
        'marketing_consent_at' => now(),
    ]);

    Booking::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'service_snapshot' => ['name' => 'Massage Body', 'price_idr' => 350000],
        'code' => 'BK-INDAH-1',
        'status_category' => BookingStatusCategory::COMPLETED,
        'start_at' => now()->subDays(3),
        'end_at' => now()->subDays(3)->addHour(),
        'total_idr' => 350000,
        'paid_amount_idr' => 350000,
        'payment_status' => 'PAID',
    ]);

    $response = $this->actingAs($owner)->getJson(route('owner.customers.show', $customer->id));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'customer' => ['id', 'name', 'phone_e164'],
        'stats' => [
            'total_bookings',
            'completed_bookings',
            'cancelled_bookings',
            'no_show_bookings',
            'total_spent_idr',
            'has_marketing_consent',
            'is_anonymized',
        ],
        'bookings',
    ]);

    $data = $response->json();
    expect($data['stats']['total_bookings'])->toBe(1)
        ->and($data['stats']['completed_bookings'])->toBe(1)
        ->and($data['stats']['total_spent_idr'])->toBe(350000)
        ->and($data['stats']['has_marketing_consent'])->toBeTrue()
        ->and($data['stats']['is_anonymized'])->toBeFalse();
});

test('tenant isolation: tenant A cannot append note or anonymize customer of tenant B', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $ownerA = $envA['user'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    /** @var Tenant $tenantB */
    $tenantB = $envB['tenant'];

    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Beta Customer',
        'phone_e164' => '+628999888777',
    ]);

    // Owner A attempts to append note to Customer B
    $responseNote = $this->actingAs($ownerA)->post(route('owner.customers.notes.store', $customerB->id), [
        'note' => 'Catatan penyusup',
    ]);
    $responseNote->assertNotFound();

    // Owner A attempts to anonymize Customer B
    $responseAnon = $this->actingAs($ownerA)->post(route('owner.customers.anonymize', $customerB->id));
    $responseAnon->assertNotFound();
});
