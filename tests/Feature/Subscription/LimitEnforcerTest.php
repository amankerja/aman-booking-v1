<?php

namespace Tests\Feature\Subscription;

use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Exceptions\PlanLimitReachedException;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Services\LimitEnforcer;
use App\Domain\Tenant\Models\BusinessMember;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('member limit reached rejects new member creation with friendly plan limit message', function () {
    $env = $this->createTenantEnvironment('Barber Limit Test');
    $tenant = $env['tenant'];
    $owner = $env['user'];

    // In createTenantEnvironment, the owner is already 1 member.
    // BASIC plan has max_members = 3.
    // Let's add 2 more members so we reach 3 members.
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => User::factory()->create()->id,
        'preset' => 'STAFF',
    ]);
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => User::factory()->create()->id,
        'preset' => 'FRONT_DESK',
    ]);

    expect(BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(3);

    // LimitEnforcer canCreate should now return false for members
    $enforcer = app(LimitEnforcer::class);
    expect($enforcer->canCreate('members', $tenant))->toBeFalse();

    // Direct enforce() call throws PlanLimitReachedException
    expect(fn () => $enforcer->enforce('members', $tenant))
        ->toThrow(PlanLimitReachedException::class, 'Batas paket Anda sudah tercapai. Upgrade paket untuk menambah.');

    // HTTP POST to /app/members should be rejected with 403
    $response = $this->actingAs($owner)->postJson('/app/members', [
        'name' => 'Calon Member Ke-4',
        'email' => 'member4@barber.test',
        'preset' => 'STAFF',
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'error' => [
            'code' => 'PLAN_LIMIT_REACHED',
            'message' => 'Batas paket Anda sudah tercapai. Upgrade paket untuk menambah.',
        ],
    ]);

    // Verify member count did not increase
    expect(BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(3);
});

test('existing data can still be read even when limit is reached', function () {
    $env = $this->createTenantEnvironment('Barber Read Test');
    $tenant = $env['tenant'];
    $owner = $env['user'];

    // Add members up to limit (3 members total)
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => User::factory()->create()->id,
        'preset' => 'STAFF',
    ]);
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => User::factory()->create()->id,
        'preset' => 'FRONT_DESK',
    ]);

    // GET /app/members still succeeds and returns the 3 members
    $response = $this->actingAs($owner)->getJson('/app/members');

    $response->assertStatus(200);
    $response->assertJsonCount(3, 'members');
});

test('unlimited or higher tier plans allow creating beyond standard limits', function () {
    $env = $this->createTenantEnvironment('Pro Clinic Test');
    $tenant = $env['tenant'];

    // Upgrade tenant subscription to PRO plan (max_members = 10)
    $proPlan = Plan::where('code', 'PRO')->firstOrFail();
    $env['subscription']->update(['plan_id' => $proPlan->id]);

    $enforcer = app(LimitEnforcer::class);

    // With 1 owner member, PRO plan has 9 remaining
    $usage = $enforcer->getUsage('members', $tenant);
    expect($usage['current'])->toBe(1)
        ->and($usage['limit'])->toBe(10)
        ->and($usage['remaining'])->toBe(9)
        ->and($enforcer->canCreate('members', $tenant))->toBeTrue();
});
