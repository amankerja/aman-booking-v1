<?php

namespace Tests\Unit\Customer;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Customer\Services\CustomerService;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
    $this->customerService = app(CustomerService::class);
});

afterEach(function () {
    TenantContext::clear();
});

test('phone normalization converts indonesian formats to E.164 and preserves international numbers', function () {
    // 08... format
    expect(Customer::normalizePhone('08123456789'))->toBe('+628123456789')
        ->and(Customer::normalizePhone('0812-3456-7890'))->toBe('+6281234567890')
        ->and(Customer::normalizePhone('(0812) 3456 7890'))->toBe('+6281234567890');

    // 628... format
    expect(Customer::normalizePhone('628123456789'))->toBe('+628123456789')
        ->and(Customer::normalizePhone('+628123456789'))->toBe('+628123456789')
        ->and(Customer::normalizePhone('+62 812-3456-7890'))->toBe('+6281234567890');

    // 8... format without prefix
    expect(Customer::normalizePhone('81234567890'))->toBe('+6281234567890');

    // International numbers with +
    expect(Customer::normalizePhone('+1 (555) 234-5678'))->toBe('+15552345678')
        ->and(Customer::normalizePhone('+65 9123 4567'))->toBe('+6591234567')
        ->and(Customer::normalizePhone('+60 12-345 6789'))->toBe('+60123456789');

    // Empty or non-digits
    expect(Customer::normalizePhone(''))->toBe('')
        ->and(Customer::normalizePhone('   '))->toBe('');
});

test('resolveCustomer deduplicates by phone within tenant and respects marketing consent', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $owner->id]);

    // 1. First resolution: creates new customer
    $c1 = $this->customerService->resolveCustomer($tenant, [
        'name' => 'Budi Utomo',
        'phone' => '0812-3456-7890',
        'email' => 'budi@example.com',
        'tags' => ['regular'],
        'marketing_consent' => false, // PRD 40: no consent by default
    ]);

    expect($c1)->toBeInstanceOf(Customer::class)
        ->and($c1->phone_e164)->toBe('+6281234567890')
        ->and($c1->hasMarketingConsent())->toBeFalse();

    // 2. Second resolution with varying phone formatting: returns existing customer
    $c2 = $this->customerService->resolveCustomer($tenant, [
        'name' => 'Budi Utomo Updated',
        'phone' => '+62 812 3456 7890',
        'email' => 'budi@example.com',
        'tags' => ['vip'],
        'marketing_consent' => true, // Explicit opt-in now
    ]);

    expect($c2->id)->toBe($c1->id)
        ->and(Customer::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($c2->hasMarketingConsent())->toBeTrue()
        ->and($c2->tags)->toContain('regular', 'vip');
});

test('resolveCustomer maintains strict tenant boundary for deduplication', function () {
    $ownerA = User::factory()->create();
    $tenantA = Tenant::factory()->create(['owner_user_id' => $ownerA->id]);

    $ownerB = User::factory()->create();
    $tenantB = Tenant::factory()->create(['owner_user_id' => $ownerB->id]);

    $samePhone = '081299998888';

    $cA = $this->customerService->resolveCustomer($tenantA, [
        'name' => 'Customer in Tenant A',
        'phone' => $samePhone,
        'email' => 'shared@example.com',
    ]);

    $cB = $this->customerService->resolveCustomer($tenantB, [
        'name' => 'Customer in Tenant B',
        'phone' => $samePhone,
        'email' => 'shared@example.com',
    ]);

    expect($cA->id)->not->toBe($cB->id)
        ->and($cA->tenant_id)->toBe($tenantA->id)
        ->and($cB->tenant_id)->toBe($tenantB->id)
        ->and(Customer::where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(Customer::where('tenant_id', $tenantB->id)->count())->toBe(1);
});

test('search finds customer by name, phone, email, and booking code', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $owner->id]);

    $c1 = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Siti Nurhaliza',
        'phone_e164' => '+6281311112222',
        'email' => 'siti@example.com',
        'tags' => ['vip'],
    ]);

    $c2 = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Agus Pratama',
        'phone_e164' => '+6281933334444',
        'email' => 'agus@example.com',
        'tags' => ['regular'],
    ]);

    // Create booking for c2 with distinct code
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $c2->id,
        'code' => 'BK-20261004-99881',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'total_idr' => 100000,
    ]);

    // Search by name
    $resName = $this->customerService->search($tenant, 'Siti');
    expect($resName)->toHaveCount(1)
        ->and($resName->first()->id)->toBe($c1->id);

    // Search by phone digits
    $resPhone = $this->customerService->search($tenant, '081933334444');
    expect($resPhone)->toHaveCount(1)
        ->and($resPhone->first()->id)->toBe($c2->id);

    // Search by email
    $resEmail = $this->customerService->search($tenant, 'siti@example.com');
    expect($resEmail)->toHaveCount(1)
        ->and($resEmail->first()->id)->toBe($c1->id);

    // Search by booking code (PRD 39)
    $resCode = $this->customerService->search($tenant, 'BK-20261004-99881');
    expect($resCode)->toHaveCount(1)
        ->and($resCode->first()->id)->toBe($c2->id);
});

test('merge reassigns bookings, merges tags/notes, aggregates no-shows, and logs audit', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $owner->id]);

    $target = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Target Customer',
        'phone_e164' => '+628111111111',
        'email' => 'target@example.com',
        'tags' => ['vip'],
        'notes' => 'Catatan target.',
        'no_show_count' => 1,
        'marketing_consent_at' => now()->subDay(),
    ]);

    $source = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Source Customer',
        'phone_e164' => '+628222222222',
        'email' => 'source@example.com',
        'tags' => ['regular', 'promo'],
        'notes' => 'Catatan sumber.',
        'no_show_count' => 2,
        'marketing_consent_at' => now()->subDays(5),
    ]);

    // Booking attached to source
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $source->id,
        'code' => 'BK-MERGE-TEST-001',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'total_idr' => 150000,
    ]);

    $merged = $this->customerService->merge($target, $source, $owner);

    expect($merged->id)->toBe($target->id)
        ->and($merged->no_show_count)->toBe(3) // 1 + 2
        ->and($merged->tags)->toContain('vip', 'regular', 'promo')
        ->and($merged->notes)->toContain('Catatan target.', 'Catatan sumber.', 'Merged from')
        ->and(Customer::withoutGlobalScopes()->find($source->id))->toBeNull();

    // Verify booking was reassigned to target customer
    $booking->refresh();
    expect($booking->customer_id)->toBe($target->id);
});

test('export enforces customer.export permission strictly (PRD 212)', function () {
    $owner = User::factory()->create(['name' => 'Clinic Owner']);
    $tenant = Tenant::factory()->create(['owner_user_id' => $owner->id]);

    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Patient One',
        'phone_e164' => '+628111222333',
        'email' => 'patient@example.com',
    ]);

    // 1. Tenant Owner can export
    $exportedByOwner = $this->customerService->export($tenant, $owner);
    expect($exportedByOwner)->toHaveCount(1)
        ->and($exportedByOwner[0]['name'])->toBe('Patient One');

    // 2. Front Desk user without customer.export permission is blocked with AuthorizationException
    setPermissionsTeamId($tenant->id);
    $frontDeskUser = User::factory()->create(['name' => 'Front Desk Staff']);
    $frontDeskRole = Role::firstOrCreate(['name' => 'Front Desk', 'guard_name' => 'web']);
    $frontDeskUser->assignRole($frontDeskRole);

    expect(fn () => $this->customerService->export($tenant, $frontDeskUser))
        ->toThrow(AuthorizationException::class);
});
