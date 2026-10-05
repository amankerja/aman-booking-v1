<?php

namespace Tests\Feature\Tenant;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('user tenant A cannot see or access tenant B business data', function () {
    $tenantA = $this->createTenantEnvironment('Salon Cantik');
    $tenantB = $this->createTenantEnvironment('Bengkel Jaya');

    // 1. Check Owner Dashboard under Tenant A
    $response = $this->actingAs($tenantA['user'])->get(route('owner.dashboard'));
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Dashboard')
        ->where('tenant.id', $tenantA['tenant']->id)
        ->where('business.id', $tenantA['business']->id)
        ->where('business.name', 'Salon Cantik Business')
    );

    // 2. Direct Eloquent isolation check under Tenant A context
    TenantContext::setTenant($tenantA['tenant']);

    // Tenant A can see its own business
    $myBusiness = Business::find($tenantA['business']->id);
    expect($myBusiness)->not->toBeNull()
        ->and($myBusiness->id)->toBe($tenantA['business']->id);

    // Tenant A CANNOT see Tenant B's business by ID (returns null because scoped)
    $otherBusiness = Business::find($tenantB['business']->id);
    expect($otherBusiness)->toBeNull();

    // Querying all businesses under Tenant A returns only Tenant A's business
    $businesses = Business::all();
    expect($businesses)->toHaveCount(1)
        ->and($businesses->first()->id)->toBe($tenantA['business']->id);
});

test('user tenant A cannot modify tenant B data via scoped query', function () {
    $tenantA = $this->createTenantEnvironment('Tenant Alfa');
    $tenantB = $this->createTenantEnvironment('Tenant Beta');

    TenantContext::setTenant($tenantA['tenant']);

    // Attempt to update Tenant B's business while in Tenant A context
    $affected = Business::where('id', $tenantB['business']->id)->update([
        'name' => 'Hacked by Tenant Alfa',
    ]);

    // 0 rows affected because TenantScope injected where tenant_id = tenantA->id
    expect($affected)->toBe(0);

    // Verify Tenant B's data remains unmodified
    $bFresh = Business::withoutGlobalScopes()->find($tenantB['business']->id);
    expect($bFresh->name)->toBe('Tenant Beta Business');
});

test('spatie roles and permissions are strictly isolated per tenant team', function () {
    $tenantA = $this->createTenantEnvironment('Studio Foto A');
    $tenantB = $this->createTenantEnvironment('Studio Foto B');

    // Assign Owner role in Tenant A
    setPermissionsTeamId($tenantA['tenant']->id);
    $tenantA['user']->assignRole('Owner');

    // Assert User A has role in Tenant A
    expect($tenantA['user']->hasRole('Owner'))->toBeTrue();

    // Switch context to Tenant B
    setPermissionsTeamId($tenantB['tenant']->id);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $userA = $tenantA['user']->fresh();

    // Assert User A DOES NOT have role in Tenant B
    expect($userA->hasRole('Owner'))->toBeFalse();
    expect($userA->can('tenant.manage'))->toBeFalse();
});

test('query without tenant scope is detected and prevented in strict mode', function () {
    $tenant = $this->createTenantEnvironment('Strict Test');

    TenantContext::clear();
    TenantContext::setStrict(true);

    expect(fn () => Business::count())
        ->toThrow(RuntimeException::class, 'Query executed on tenant model');
});

test('all tenant domain models have TenantScope registered globally', function () {
    // Whitelisted models:
    // - Tenant (the root entity itself, does not belong to another tenant)
    // - User (global entity across tenants)
    // - Plan (system-wide subscription plans)
    // - AuditLog (can record system and tenant activities)
    // - ResourceType (supports global system presets with nullable tenant_id + tenant custom types)
    // - SystemTemplate & SystemTemplateVersion (platform-wide templates with versioning)
    $whitelist = [
        Tenant::class,
        User::class,
        AuditLog::class,
        ResourceType::class,
        \App\Domain\Template\Models\SystemTemplate::class,
        \App\Domain\Template\Models\SystemTemplateVersion::class,
    ];

    $this->assertAllTenantModelsHaveScope($whitelist);
});

test('middleware ResolveTenant resolves tenant via header and session', function () {
    $tenant = $this->createTenantEnvironment('Header Tenant');

    // 1. Resolve via X-Tenant-ID header
    $response = $this->withHeader('X-Tenant-ID', (string) $tenant['tenant']->id)
        ->get('/');
    $response->assertStatus(200);
    expect(TenantContext::getTenantId())->toBe($tenant['tenant']->id);

    // 2. Clear context
    TenantContext::clear();

    // 3. Resolve via authenticated user session
    $this->actingAs($tenant['user'])->get(route('owner.dashboard'));
    expect(TenantContext::getTenantId())->toBe($tenant['tenant']->id);
});
