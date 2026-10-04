<?php

namespace Tests\Feature\Subscription;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('suspended subscription closes public booking page with 503 TENANT_UNAVAILABLE', function () {
    $env = $this->createTenantEnvironment('Suspended Spa');
    $business = $env['business'];
    $subscription = $env['subscription'];

    // Suspend subscription
    $subscription->update(['status' => 'SUSPENDED']);

    // Attempt to access public booking page
    $response = $this->get('/b/'.$business->slug);

    $response->assertStatus(503);
});

test('active or trial subscription allows public booking page access', function () {
    $env = $this->createTenantEnvironment('Active Barbershop');
    $business = $env['business'];

    $response = $this->get('/b/'.$business->slug);

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Booking')
        ->where('business.slug', $business->slug)
    );
});

test('grace_period and expired subscriptions allow read-only access but block mutations', function () {
    $env = $this->createTenantEnvironment('Grace Clinic');
    $owner = $env['user'];
    $subscription = $env['subscription'];

    // 1. Set to GRACE_PERIOD
    $subscription->update(['status' => 'GRACE_PERIOD']);

    // Read access is allowed (GET dashboard & GET members)
    $readResponse = $this->actingAs($owner)->get('/app/dashboard');
    $readResponse->assertStatus(200);

    $membersRead = $this->actingAs($owner)->getJson('/app/members');
    $membersRead->assertStatus(200);

    // Mutation is BLOCKED (POST /app/members returns 403)
    $mutationResponse = $this->actingAs($owner)->postJson('/app/members', [
        'name' => 'Dokter Baru',
        'email' => 'dokter@grace.test',
        'preset' => 'STAFF',
    ]);

    $mutationResponse->assertStatus(403);
    $mutationResponse->assertJson([
        'error' => [
            'code' => 'SUBSCRIPTION_READ_ONLY',
        ],
    ]);

    // 2. Set to EXPIRED
    $subscription->update(['status' => 'EXPIRED']);

    // Read still allowed
    $this->actingAs($owner)->get('/app/dashboard')->assertStatus(200);

    // Mutation still BLOCKED
    $expiredMutation = $this->actingAs($owner)->postJson('/app/members', [
        'name' => 'Dokter Lain',
        'email' => 'dokter2@grace.test',
        'preset' => 'STAFF',
    ]);
    $expiredMutation->assertStatus(403);
});

test('suspended subscription blocks mutations on dashboard', function () {
    $env = $this->createTenantEnvironment('Suspended Tenant');
    $owner = $env['user'];
    $subscription = $env['subscription'];

    $subscription->update(['status' => 'SUSPENDED']);

    $response = $this->actingAs($owner)->postJson('/app/members', [
        'name' => 'Staff Baru',
        'email' => 'staff@suspended.test',
        'preset' => 'STAFF',
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'error' => [
            'code' => 'SUBSCRIPTION_SUSPENDED',
        ],
    ]);
});
