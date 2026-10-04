<?php

use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('database has all core foundation tables with tenant_id indexes', function () {
    $tables = [
        'users',
        'tenants',
        'businesses',
        'business_members',
        'plans',
        'subscriptions',
        'subscription_usage',
        'audit_logs',
        'idempotency_keys',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");
    }

    // Verify tenant_id column exists on tenant-owned tables
    $tenantTables = [
        'businesses',
        'business_members',
        'subscriptions',
        'subscription_usage',
        'audit_logs',
        'idempotency_keys',
    ];

    foreach ($tenantTables as $table) {
        expect(Schema::hasColumn($table, 'tenant_id'))->toBeTrue("Table {$table} must have tenant_id column");
    }
});

test('database seeder seeds standard plans and super admin dev user', function () {
    $this->seed();

    expect(Plan::where('code', 'BASIC')->exists())->toBeTrue()
        ->and(Plan::where('code', 'PRO')->exists())->toBeTrue()
        ->and(Plan::where('code', 'BUSINESS')->exists())->toBeTrue();

    $superAdmin = User::where('email', 'superadmin@amanbooking.com')->first();
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->is_super_admin)->toBeTrue();
});
