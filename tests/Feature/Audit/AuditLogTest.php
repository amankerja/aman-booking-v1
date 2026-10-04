<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\BusinessMember;
use App\Support\Audit;
use App\Support\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlanSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('login and logout generate audit log entries', function () {
    $env = $this->createTenantEnvironment('Login Audit Tenant');
    $user = $env['user'];
    $user->update(['password' => Hash::make('password123')]);

    // 1. Perform login
    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $loginLog = AuditLog::where('action', 'user.login')
        ->where('actor_id', $user->id)
        ->first();

    expect($loginLog)->not->toBeNull()
        ->and($loginLog->entity_type)->toBe('User')
        ->and($loginLog->entity_id)->toBe((string) $user->id);

    // 2. Perform logout
    $this->actingAs($user)->post('/logout');

    $logoutLog = AuditLog::where('action', 'user.logout')
        ->where('actor_id', $user->id)
        ->first();

    expect($logoutLog)->not->toBeNull()
        ->and($logoutLog->entity_type)->toBe('User');
});

test('member creation and update generate auditable events', function () {
    $env = $this->createTenantEnvironment('Member Audit Tenant');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $newUser = User::factory()->create();

    // 1. Create member
    $member = BusinessMember::create([
        'tenant_id' => $tenant->id,
        'user_id' => $newUser->id,
        'preset' => 'STAFF',
    ]);

    $createLog = AuditLog::where('action', 'business_member.created')
        ->where('entity_id', (string) $member->id)
        ->first();

    expect($createLog)->not->toBeNull()
        ->and($createLog->tenant_id)->toBe($tenant->id)
        ->and($createLog->after)->toBeArray()
        ->and($createLog->after['preset'])->toBe('STAFF');

    // 2. Update member preset
    $member->update(['preset' => 'MANAGER']);

    $updateLog = AuditLog::where('action', 'business_member.updated')
        ->where('entity_id', (string) $member->id)
        ->first();

    expect($updateLog)->not->toBeNull()
        ->and($updateLog->before['preset'])->toBe('STAFF')
        ->and($updateLog->after['preset'])->toBe('MANAGER');
});

test('subscription updates generate audit logs', function () {
    $env = $this->createTenantEnvironment('Subscription Audit Tenant');
    $subscription = $env['subscription'];

    $subscription->update(['status' => 'ACTIVE']);

    $log = AuditLog::where('action', 'subscription.updated')
        ->where('entity_id', (string) $subscription->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($env['tenant']->id)
        ->and($log->before['status'])->toBe('TRIAL')
        ->and($log->after['status'])->toBe('ACTIVE');
});

test('sensitive data is never stored in audit payloads', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'super-secret-password-123',
        'password_confirmation' => 'super-secret-password-123',
        'remember_token' => 'random-remember-token',
        'nested' => [
            'api_key' => 'secret_api_key_456',
            'public_info' => 'allowed',
        ],
    ];

    $sanitized = Audit::sanitize($payload);

    expect($sanitized['name'])->toBe('John Doe')
        ->and($sanitized['password'])->toBe('[REDACTED]')
        ->and($sanitized['password_confirmation'])->toBe('[REDACTED]')
        ->and($sanitized['remember_token'])->toBe('[REDACTED]')
        ->and($sanitized['nested']['api_key'])->toBe('[REDACTED]')
        ->and($sanitized['nested']['public_info'])->toBe('allowed');

    // Also verify via direct Audit::record call
    $log = Audit::record([
        'action' => 'user.credentials_changed',
        'entity_type' => 'User',
        'entity_id' => 999,
        'before' => ['password' => 'old_secret'],
        'after' => ['password' => 'new_secret'],
    ]);

    expect($log->before['password'])->toBe('[REDACTED]')
        ->and($log->after['password'])->toBe('[REDACTED]');
});

test('owner can view tenant audit logs with isolation and filtering', function () {
    $tenantA = $this->createTenantEnvironment('Tenant Alfa');
    $tenantB = $this->createTenantEnvironment('Tenant Beta');

    // Create logs for Tenant A
    Audit::record([
        'tenant_id' => $tenantA['tenant']->id,
        'action' => 'business_member.created',
        'entity_type' => 'BusinessMember',
        'entity_id' => 101,
    ]);
    Audit::record([
        'tenant_id' => $tenantA['tenant']->id,
        'action' => 'subscription.updated',
        'entity_type' => 'Subscription',
        'entity_id' => 102,
    ]);

    // Create log for Tenant B
    Audit::record([
        'tenant_id' => $tenantB['tenant']->id,
        'action' => 'business_member.created',
        'entity_type' => 'BusinessMember',
        'entity_id' => 201,
    ]);

    // Owner A requests audit logs
    $response = $this->actingAs($tenantA['user'])->get('/app/audit-logs');
    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/AuditLogs')
        ->has('logs.data', 4) // 2 from createTenantEnvironment + 2 created above
        ->where('logs.data.0.tenant_id', $tenantA['tenant']->id)
    );

    // Verify Tenant B's entity_id 201 is NEVER present in Tenant A's results
    $pageProps = $response->viewData('page')['props'];
    $entityIds = collect($pageProps['logs']['data'])->pluck('entity_id')->all();
    expect($entityIds)->not->toContain('201');

    // Test action filter
    $filteredResponse = $this->actingAs($tenantA['user'])->get('/app/audit-logs?action=subscription.updated');
    $filteredResponse->assertStatus(200);
    $filteredProps = $filteredResponse->viewData('page')['props'];
    expect($filteredProps['logs']['data'])->toHaveCount(1)
        ->and($filteredProps['logs']['data'][0]['action'])->toBe('subscription.updated');
});
