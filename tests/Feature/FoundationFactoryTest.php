<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionUsage;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user factory can create regular and super admin users', function () {
    $user = User::factory()->create();
    expect($user->is_super_admin)->toBeFalse();

    $admin = User::factory()->superAdmin()->create();
    expect($admin->is_super_admin)->toBeTrue();
});

test('tenant factory creates a valid tenant with uuid and owner', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->uuid)->not->toBeEmpty()
        ->and($tenant->owner)->toBeInstanceOf(User::class)
        ->and($tenant->status)->toBe('ACTIVE');
});

test('business factory creates business linked to tenant with valid slug', function () {
    $business = Business::factory()->create();

    expect($business->tenant)->toBeInstanceOf(Tenant::class)
        ->and($business->slug)->not->toBeEmpty()
        ->and($business->uuid)->not->toBeEmpty();
});

test('business member factory associates user and tenant', function () {
    $member = BusinessMember::factory()->create([
        'preset' => 'MANAGER',
    ]);

    expect($member->tenant)->toBeInstanceOf(Tenant::class)
        ->and($member->user)->toBeInstanceOf(User::class)
        ->and($member->preset)->toBe('MANAGER');
});

test('plan and subscription factories work as expected', function () {
    $plan = Plan::factory()->create([
        'code' => 'CUSTOM',
        'price_idr' => 200000,
    ]);

    $subscription = Subscription::factory()->create([
        'plan_id' => $plan->id,
    ]);

    expect($subscription->plan->code)->toBe('CUSTOM')
        ->and($subscription->status)->toBe('ACTIVE');

    $trial = Subscription::factory()->trial()->create();
    expect($trial->status)->toBe('TRIAL')
        ->and($trial->trial_ends_at)->not->toBeNull();
});

test('subscription usage factory tracks metric counts', function () {
    $usage = SubscriptionUsage::factory()->create([
        'metric' => 'monthly_bookings',
        'usage_count' => 15,
    ]);

    expect($usage->subscription)->toBeInstanceOf(Subscription::class)
        ->and($usage->metric)->toBe('monthly_bookings')
        ->and($usage->usage_count)->toBe(15);
});

test('audit log factory records activity accurately', function () {
    $log = AuditLog::factory()->create([
        'action' => 'tenant.created',
        'entity_type' => 'Tenant',
    ]);

    expect($log->tenant)->toBeInstanceOf(Tenant::class)
        ->and($log->action)->toBe('tenant.created')
        ->and($log->created_at)->not->toBeNull();
});

test('idempotency key factory stores API idempotency records', function () {
    $key = IdempotencyKey::factory()->create([
        'key' => 'idemp-12345',
        'endpoint' => '/api/v1/bookings',
    ]);

    expect($key->tenant)->toBeInstanceOf(Tenant::class)
        ->and($key->key)->toBe('idemp-12345')
        ->and($key->status_code)->toBe(200);
});
