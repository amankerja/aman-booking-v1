<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Jobs\UpdateSubscriptionStatusesJob;
use App\Domain\Subscription\Models\Subscription;
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

test('daily job transitions expired trial to expired status', function () {
    $env = $this->createTenantEnvironment('Expired Trial');
    $subscription = $env['subscription'];

    // Set trial end date to 2 days ago
    $subscription->update([
        'status' => 'TRIAL',
        'trial_ends_at' => now()->subDays(2),
        'grace_ends_at' => null,
    ]);

    // Dispatch the status update job
    UpdateSubscriptionStatusesJob::dispatchSync();

    expect($subscription->fresh()->status)->toBe('EXPIRED');
});

test('daily job transitions trial with active grace period to grace_period status', function () {
    $env = $this->createTenantEnvironment('Grace Trial');
    $subscription = $env['subscription'];

    // Set trial end date to yesterday, but grace ends in 3 days
    $subscription->update([
        'status' => 'TRIAL',
        'trial_ends_at' => now()->subDay(),
        'grace_ends_at' => now()->addDays(3),
    ]);

    UpdateSubscriptionStatusesJob::dispatchSync();

    expect($subscription->fresh()->status)->toBe('GRACE_PERIOD');
});

test('daily job transitions overdue active subscription to past_due or expired', function () {
    $env = $this->createTenantEnvironment('Overdue Active');
    $subscription = $env['subscription'];

    // Active subscription overdue without grace
    $subscription->update([
        'status' => 'ACTIVE',
        'current_period_end' => now()->subDay(),
        'grace_ends_at' => null,
    ]);

    UpdateSubscriptionStatusesJob::dispatchSync();

    expect($subscription->fresh()->status)->toBe('PAST_DUE');

    // Overdue subscription where grace period also passed
    $subscription->update([
        'status' => 'PAST_DUE',
        'grace_ends_at' => now()->subHour(),
    ]);

    UpdateSubscriptionStatusesJob::dispatchSync();

    expect($subscription->fresh()->status)->toBe('EXPIRED');
});

test('subscriptions:update-statuses artisan command runs successfully', function () {
    $this->artisan('subscriptions:update-statuses')
        ->expectsOutput('Evaluating subscription statuses...')
        ->expectsOutput('Subscription statuses updated successfully.')
        ->assertExitCode(0);
});
