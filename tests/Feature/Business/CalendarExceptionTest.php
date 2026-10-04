<?php

namespace Tests\Feature\Business;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\CalendarException;
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

test('owner can view calendar exceptions list page', function () {
    $env = $this->createTenantEnvironment('Salon Elit');

    CalendarException::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'type' => 'HOLIDAY',
        'date' => '2026-12-25',
        'title' => 'Hari Natal',
        'is_closed' => true,
    ]);

    $response = $this->actingAs($env['user'])->get(route('owner.settings.calendar'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Settings/CalendarExceptions')
        ->has('exceptions', 1)
        ->where('exceptions.0.title', 'Hari Natal')
        ->where('exceptions.0.is_closed', true)
        ->where('business.id', $env['business']->id)
        ->has('types', 4)
    );
});

test('owner can create holiday exception with full closure', function () {
    $env = $this->createTenantEnvironment('Bengkel Prima');

    $response = $this->actingAs($env['user'])->post(route('owner.settings.calendar.store'), [
        'type' => 'HOLIDAY',
        'date' => '2026-08-17',
        'title' => 'HUT Kemerdekaan RI',
        'is_closed' => true,
        'note' => 'Bengkel tutup operasional seharian.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $exception = CalendarException::where('business_id', $env['business']->id)
        ->where('date', '2026-08-17')
        ->first();

    expect($exception)->not->toBeNull()
        ->and($exception->title)->toBe('HUT Kemerdekaan RI')
        ->and($exception->is_closed)->toBeTrue()
        ->and($exception->open_time)->toBeNull();

    // Audit log
    $auditLog = AuditLog::where('action', 'calendar_exception.created')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id);
});

test('owner can create special operating hours exception', function () {
    $env = $this->createTenantEnvironment('Resto Rasa');

    $response = $this->actingAs($env['user'])->post(route('owner.settings.calendar.store'), [
        'type' => 'SPECIAL_OPEN',
        'date' => '2026-12-31',
        'title' => 'Malam Tahun Baru',
        'is_closed' => false,
        'open_time' => '10:00',
        'close_time' => '23:30',
        'note' => 'Buka lebih lama untuk perayaan tahun baru.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $exception = CalendarException::where('business_id', $env['business']->id)
        ->where('date', '2026-12-31')
        ->first();

    expect($exception)->not->toBeNull()
        ->and($exception->type)->toBe('SPECIAL_OPEN')
        ->and($exception->is_closed)->toBeFalse()
        ->and(substr($exception->open_time, 0, 5))->toBe('10:00')
        ->and(substr($exception->close_time, 0, 5))->toBe('23:30');
});

test('validation requires open and close times when is_closed is false', function () {
    $env = $this->createTenantEnvironment('Gym Maju');

    $response = $this->actingAs($env['user'])->post(route('owner.settings.calendar.store'), [
        'type' => 'SPECIAL_OPEN',
        'date' => '2026-10-10',
        'title' => 'Event Pembukaan',
        'is_closed' => false,
        'open_time' => null, // missing open time
        'close_time' => null, // missing close time
    ]);

    $response->assertSessionHasErrors(['open_time', 'close_time']);
});

test('owner can delete calendar exception', function () {
    $env = $this->createTenantEnvironment('Barbershop Cool');

    $exception = CalendarException::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'type' => 'BLACKOUT',
        'date' => '2026-11-05',
        'title' => 'Renovasi Interior',
        'is_closed' => true,
    ]);

    $response = $this->actingAs($env['user'])->delete(route('owner.settings.calendar.destroy', $exception->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(CalendarException::find($exception->id))->toBeNull();

    // Audit log
    $auditLog = AuditLog::where('action', 'calendar_exception.deleted')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id);
});

test('tenant cannot delete another tenant calendar exception', function () {
    $envA = $this->createTenantEnvironment('Tenant X');
    $envB = $this->createTenantEnvironment('Tenant Y');

    $exceptionB = CalendarException::withoutGlobalScopes()->create([
        'tenant_id' => $envB['tenant']->id,
        'business_id' => $envB['business']->id,
        'type' => 'HOLIDAY',
        'date' => '2026-05-01',
        'title' => 'Hari Buruh',
        'is_closed' => true,
    ]);

    // User A attempts to delete User B exception
    $response = $this->actingAs($envA['user'])->delete(route('owner.settings.calendar.destroy', $exceptionB->id));

    $response->assertStatus(404);

    // Exception B remains safe
    expect(CalendarException::withoutGlobalScopes()->find($exceptionB->id))->not->toBeNull();
});
