<?php

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('super admin can access admin dashboard while regular owner is denied', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $owner = User::factory()->create();

    // Super Admin can access admin dashboard
    $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $response->assertStatus(200);

    // Regular owner is denied with 403
    $response = $this->actingAs($owner)->get(route('admin.dashboard'));
    $response->assertStatus(403);
});

test('role presets grant expected permissions within tenant', function () {
    $env = $this->createTenantEnvironment('Role Test Clinic');
    $tenant = $env['tenant'];

    // Set Spatie Team to this tenant
    setPermissionsTeamId($tenant->id);

    // 1. Manager User
    $managerUser = User::factory()->create(['name' => 'Manager User']);
    $managerUser->assignRole('Manager');
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $managerUser->id,
        'preset' => 'MANAGER',
    ]);

    expect($managerUser->can('service.manage'))->toBeTrue()
        ->and($managerUser->can('booking.update'))->toBeTrue()
        ->and($managerUser->can('report.view'))->toBeTrue()
        ->and($managerUser->can('tenant.manage'))->toBeFalse(); // Manager cannot manage tenant billing/deletion

    // 2. Front Desk User
    $frontDeskUser = User::factory()->create(['name' => 'Front Desk User']);
    $frontDeskUser->assignRole('Front Desk');

    expect($frontDeskUser->can('booking.create'))->toBeTrue()
        ->and($frontDeskUser->can('booking.checkin'))->toBeTrue()
        ->and($frontDeskUser->can('service.manage'))->toBeFalse()
        ->and($frontDeskUser->can('report.export'))->toBeFalse();

    // 3. Staff User
    $staffUser = User::factory()->create(['name' => 'Staff User']);
    $staffUser->assignRole('Staff');

    expect($staffUser->can('booking.checkin'))->toBeTrue()
        ->and($staffUser->can('service.view'))->toBeTrue()
        ->and($staffUser->can('customer.view'))->toBeFalse();

    // 4. Viewer User
    $viewerUser = User::factory()->create(['name' => 'Viewer User']);
    $viewerUser->assignRole('Viewer');

    expect($viewerUser->can('report.view'))->toBeTrue()
        ->and($viewerUser->can('booking.create'))->toBeFalse()
        ->and($viewerUser->can('booking.update'))->toBeFalse();
});

test('member without permission is rejected on server authorization check', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $tenant = $env['tenant'];

    setPermissionsTeamId($tenant->id);

    $staffUser = User::factory()->create(['name' => 'Staff Dental']);
    $staffUser->assignRole('Staff');

    // Server-side policy/gate check: Staff cannot manage services
    $canManageServices = $staffUser->can('service.manage');
    expect($canManageServices)->toBeFalse();

    // Server-side policy/gate check: Staff cannot export customers
    $canExportCustomers = $staffUser->can('customer.export');
    expect($canExportCustomers)->toBeFalse();
});
