<?php

namespace Tests\Feature\Customer;

use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\BusinessMember;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('owner can view customer listing with pagination and search', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Dewi Sartika',
        'phone_e164' => '+6281200001111',
        'email' => 'dewi@example.com',
        'tags' => ['vip'],
    ]);

    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Bambang Soediro',
        'phone_e164' => '+6281200002222',
        'email' => 'bambang@example.com',
        'tags' => ['regular'],
    ]);

    $response = $this->actingAs($owner)->get(route('owner.customers.index'));
    $response->assertStatus(200);

    // Filter by search
    $searchResponse = $this->actingAs($owner)->get(route('owner.customers.index', ['search' => 'Dewi']));
    $searchResponse->assertStatus(200);
});

test('owner can create customer and phone is automatically normalized to E.164', function () {
    $env = $this->createTenantEnvironment('Salon Glamour');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->post(route('owner.customers.store'), [
        'name' => 'Rina Nose',
        'phone' => '0812-9876-5432',
        'email' => 'rina@example.com',
        'tags' => ['regular', 'member'],
        'notes' => 'Customer VIP baru.',
        'marketing_consent' => true,
    ]);

    $response->assertRedirect(route('owner.customers.index'));
    $response->assertSessionHas('success');

    $customer = Customer::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('email', 'rina@example.com')
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->phone_e164)->toBe('+6281298765432')
        ->and($customer->hasMarketingConsent())->toBeTrue();
});

test('owner can update customer profile', function () {
    $env = $this->createTenantEnvironment('Barbershop Bro');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Andi Wijaya',
        'phone_e164' => '+6281512345678',
        'email' => 'andi@example.com',
        'tags' => ['regular'],
    ]);

    $response = $this->actingAs($owner)->put(route('owner.customers.update', $customer->id), [
        'name' => 'Andi Wijaya Kusuma',
        'phone' => '+6281512345678',
        'email' => 'andi.kusuma@example.com',
        'tags' => ['vip', 'loyal'],
        'notes' => 'Potong rambut undercut selalu.',
        'marketing_consent' => true,
        'no_show_count' => 0,
    ]);

    $response->assertRedirect(route('owner.customers.index'));
    $customer->refresh();

    expect($customer->name)->toBe('Andi Wijaya Kusuma')
        ->and($customer->email)->toBe('andi.kusuma@example.com')
        ->and($customer->hasMarketingConsent())->toBeTrue();
});

test('owner can manually merge duplicate customers', function () {
    $env = $this->createTenantEnvironment('Beauty Center');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $target = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Target Customer',
        'phone_e164' => '+628111111111',
        'tags' => ['vip'],
        'no_show_count' => 0,
    ]);

    $source = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Duplicate Customer',
        'phone_e164' => '+628222222222',
        'tags' => ['regular'],
        'no_show_count' => 1,
    ]);

    $response = $this->actingAs($owner)->post(route('owner.customers.merge', $target->id), [
        'source_id' => $source->id,
    ]);

    $response->assertRedirect(route('owner.customers.index'));

    expect(Customer::withoutGlobalScopes()->find($source->id))->toBeNull()
        ->and($target->fresh()->no_show_count)->toBe(1)
        ->and($target->fresh()->tags)->toContain('vip', 'regular');
});

test('export endpoint is protected: front desk is denied 403, owner succeeds with CSV', function () {
    $env = $this->createTenantEnvironment('Auto Workshop');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Pak Joko',
        'phone_e164' => '+6281399998888',
        'email' => 'joko@example.com',
    ]);

    // 1. Owner can export CSV
    $ownerResponse = $this->actingAs($owner)->get(route('owner.customers.export'));
    $ownerResponse->assertStatus(200);
    $ownerResponse->assertHeader('Content-Disposition');
    expect($ownerResponse->headers->get('Content-Disposition'))->toContain('attachment; filename="customers-export-');

    // 2. Create Front Desk user (does NOT have customer.export permission)
    setPermissionsTeamId($tenant->id);
    $frontDeskUser = User::factory()->create(['name' => 'Front Desk Staff']);
    $frontDeskRole = Role::firstOrCreate(['name' => 'Front Desk', 'guard_name' => 'web']);
    $frontDeskUser->assignRole($frontDeskRole);
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $frontDeskUser->id,
        'preset' => 'FRONT_DESK',
    ]);

    $frontDeskResponse = $this->actingAs($frontDeskUser)->get(route('owner.customers.export'));
    $frontDeskResponse->assertStatus(403);
});

test('tenant isolation prevents accessing or merging customers across tenants', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $ownerA = $envA['user'];
    $tenantA = $envA['tenant'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    $tenantB = $envB['tenant'];

    $customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'name' => 'Customer of Beta',
        'phone_e164' => '+628177778888',
    ]);

    // Owner A tries to show Customer B -> 404
    $response = $this->actingAs($ownerA)->get(route('owner.customers.show', $customerB->id));
    $response->assertStatus(404);

    // Owner A tries to update Customer B -> 404
    $updateResponse = $this->actingAs($ownerA)->put(route('owner.customers.update', $customerB->id), [
        'name' => 'Hacked Name',
        'phone' => '+628177778888',
    ]);
    $updateResponse->assertStatus(404);
});
