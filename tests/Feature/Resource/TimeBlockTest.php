<?php

namespace Tests\Feature\Resource;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\TimeBlock;
use App\Support\TenantContext;
use Database\Seeders\ResourceTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ResourceTypeSeeder::class);

    $this->env = $this->createTenantEnvironment('Klinik Sehat');
    $this->user = $this->env['user'];
    $this->tenant = $this->env['tenant'];
    $this->business = $this->env['business'];

    setPermissionsTeamId($this->tenant->id);
    $this->user->assignRole('Owner');

    TenantContext::setTenant($this->tenant);

    $this->staffType = ResourceType::where('code', 'STAFF')->firstOrFail();
    $this->resource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Dokter Budi',
    ]);
});

afterEach(function () {
    TenantContext::clear();
});

test('owner can view time blocks page', function () {
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->resource->id,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(4),
        'reason' => 'Cuti Tahunan',
        'is_all_resources' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('owner.time-blocks.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Resources/TimeBlocks')
        ->has('time_blocks.data', 1)
        ->has('resources')
        ->where('time_blocks.data.0.reason', 'Cuti Tahunan')
    );
});

test('owner can create time block for specific resource', function () {
    $payload = [
        'resource_id' => $this->resource->id,
        'is_all_resources' => false,
        'start_at' => '2026-11-01 08:00',
        'end_at' => '2026-11-03 17:00',
        'reason' => 'Izin Dinas Luar',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('owner.time-blocks.store'), $payload);

    $response->assertSessionHas('success');

    $block = TimeBlock::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('reason', 'Izin Dinas Luar')
        ->first();

    expect($block)->not->toBeNull();
    expect($block->resource_id)->toBe($this->resource->id);
    expect($block->is_all_resources)->toBeFalse();

    // Audit log check
    $audit = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('action', 'time_block.created')
        ->where('entity_id', $block->id)
        ->first();

    expect($audit)->not->toBeNull();
});

test('owner can create all-resources time block', function () {
    $payload = [
        'resource_id' => null,
        'is_all_resources' => true,
        'start_at' => '2026-12-25 00:00',
        'end_at' => '2026-12-25 23:59',
        'reason' => 'Libur Hari Raya',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('owner.time-blocks.store'), $payload);

    $response->assertSessionHas('success');

    $block = TimeBlock::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('reason', 'Libur Hari Raya')
        ->first();

    expect($block)->not->toBeNull();
    expect($block->resource_id)->toBeNull();
    expect($block->is_all_resources)->toBeTrue();
});

test('validation rejects invalid time block with end before start', function () {
    $payload = [
        'resource_id' => $this->resource->id,
        'is_all_resources' => false,
        'start_at' => '2026-11-05 10:00',
        'end_at' => '2026-11-05 08:00', // invalid: end before start
        'reason' => 'Waktu Salah',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('owner.time-blocks.store'), $payload);

    $response->assertSessionHasErrors(['end_at']);
});

test('owner can delete time block', function () {
    $block = TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $this->resource->id,
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(6),
        'reason' => 'Block Sementara',
        'is_all_resources' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('owner.time-blocks.destroy', $block->id));

    $response->assertSessionHas('success');

    expect(TimeBlock::withoutGlobalScopes()->find($block->id))->toBeNull();
});

test('tenant cannot delete another tenant time block', function () {
    $envB = $this->createTenantEnvironment('Other Clinic B');
    $tenantB = $envB['tenant'];

    $blockB = TimeBlock::create([
        'tenant_id' => $tenantB->id,
        'resource_id' => null,
        'start_at' => now()->addDays(1),
        'end_at' => now()->addDays(2),
        'reason' => 'Cuti Dokter B',
        'is_all_resources' => true,
    ]);

    // User A attempts to delete Tenant B's time block -> 404
    $this->actingAs($this->user)
        ->delete(route('owner.time-blocks.destroy', $blockB->id))
        ->assertStatus(404);

    expect(TimeBlock::withoutGlobalScopes()->find($blockB->id))->not->toBeNull();
});
