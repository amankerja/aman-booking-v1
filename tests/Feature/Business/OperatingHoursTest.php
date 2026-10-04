<?php

namespace Tests\Feature\Business;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\BusinessHour;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('owner viewing operating hours auto-initializes 7 days schedule', function () {
    $env = $this->createTenantEnvironment('Spa Sejahtera');

    expect(BusinessHour::where('business_id', $env['business']->id)->count())->toBe(0);

    $response = $this->actingAs($env['user'])->get(route('owner.settings.hours'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Settings/OperatingHours')
        ->has('hours', 7)
        ->where('business.id', $env['business']->id)
    );

    // Business hours should now be saved in database for 7 days
    expect(BusinessHour::where('business_id', $env['business']->id)->count())->toBe(7);
});

test('owner can update 7-day operating hours and break intervals', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');

    // First visit to initialize
    $this->actingAs($env['user'])->get(route('owner.settings.hours'));

    $customSchedule = [
        ['day_of_week' => 0, 'is_open' => false, 'open_time' => '09:00', 'close_time' => '17:00', 'breaks' => []],
        ['day_of_week' => 1, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00', 'breaks' => [
            ['name' => 'Istirahat Siang', 'start_time' => '12:00', 'end_time' => '13:00'],
            ['name' => 'Istirahat Sore', 'start_time' => '16:00', 'end_time' => '16:30'],
        ]],
        ['day_of_week' => 2, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00', 'breaks' => []],
        ['day_of_week' => 3, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00', 'breaks' => []],
        ['day_of_week' => 4, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '20:00', 'breaks' => []],
        ['day_of_week' => 5, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '17:00', 'breaks' => []],
        ['day_of_week' => 6, 'is_open' => true, 'open_time' => '09:00', 'close_time' => '14:00', 'breaks' => []],
    ];

    $response = $this->actingAs($env['user'])->put(route('owner.settings.hours.update'), [
        'hours' => $customSchedule,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $monday = BusinessHour::where('business_id', $env['business']->id)
        ->where('day_of_week', 1)
        ->first();

    expect($monday)->not->toBeNull()
        ->and($monday->is_open)->toBeTrue()
        ->and(substr($monday->open_time, 0, 5))->toBe('08:00')
        ->and(substr($monday->close_time, 0, 5))->toBe('20:00')
        ->and($monday->breaks)->toHaveCount(2)
        ->and($monday->breaks[0]['name'])->toBe('Istirahat Siang');

    // Audit log
    $auditLog = AuditLog::where('action', 'business.hours_updated')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id);
});

test('validation rejects hours payload that does not contain all 7 days', function () {
    $env = $this->createTenantEnvironment('Car Wash');

    $incompleteSchedule = [
        ['day_of_week' => 1, 'is_open' => true, 'open_time' => '08:00', 'close_time' => '17:00'],
    ];

    $response = $this->actingAs($env['user'])->put(route('owner.settings.hours.update'), [
        'hours' => $incompleteSchedule,
    ]);

    $response->assertSessionHasErrors(['hours']);
});

test('tenant isolation ensures operating hours are scoped strictly per tenant', function () {
    $envA = $this->createTenantEnvironment('Tenant Studio A');
    $envB = $this->createTenantEnvironment('Tenant Studio B');

    // Initialize both
    $this->actingAs($envA['user'])->get(route('owner.settings.hours'));
    $this->actingAs($envB['user'])->get(route('owner.settings.hours'));

    TenantContext::setTenant($envA['tenant']);
    $hoursA = BusinessHour::where('business_id', $envA['business']->id)->get();
    expect($hoursA)->toHaveCount(7);

    // Querying with Tenant A context cannot see Tenant B hours
    $hoursB = BusinessHour::where('business_id', $envB['business']->id)->get();
    expect($hoursB)->toHaveCount(0);
});
