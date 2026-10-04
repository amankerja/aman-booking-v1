<?php

namespace Tests\Feature\Resource;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceGroup;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
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

    $this->env = $this->createTenantEnvironment('Salon Cantik');
    $this->user = $this->env['user'];
    $this->tenant = $this->env['tenant'];
    $this->business = $this->env['business'];

    setPermissionsTeamId($this->tenant->id);
    $this->user->assignRole('Owner');

    TenantContext::setTenant($this->tenant);

    $this->staffType = ResourceType::where('code', 'STAFF')->firstOrFail();
    $this->roomType = ResourceType::where('code', 'ROOM')->firstOrFail();
});

afterEach(function () {
    TenantContext::clear();
});

test('owner can view resources index page with quota and types', function () {
    Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist Anita',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('owner.resources.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Resources/Index')
        ->has('resources', 1)
        ->has('resource_types')
        ->has('quota')
        ->where('resources.0.name', 'Therapist Anita')
    );
});

test('owner can create resource with weekly schedules and skills', function () {
    $group = ResourceGroup::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Senior Therapists',
    ]);

    $payload = [
        'name' => 'Therapist Budi',
        'code' => 'TH-02',
        'resource_type_id' => $this->staffType->id,
        'group_id' => $group->id,
        'capacity' => 1,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
        'skills' => ['Massage', 'Hot Stone'],
        'schedules' => [
            [
                'day_of_week' => 1,
                'is_available' => true,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'breaks' => [
                    ['start' => '12:00', 'end' => '13:00', 'title' => 'Istirahat Siang'],
                ],
            ],
            [
                'day_of_week' => 0,
                'is_available' => false,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'breaks' => [],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->post(route('owner.resources.store'), $payload);

    $response->assertRedirect(route('owner.resources.index'));
    $response->assertSessionHas('success');

    $resource = Resource::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('name', 'Therapist Budi')
        ->first();

    expect($resource)->not->toBeNull();
    expect($resource->code)->toBe('TH-02');
    expect($resource->group_id)->toBe($group->id);
    expect($resource->skills)->toContain('Massage', 'Hot Stone');

    // Schedules check
    $schedules = ResourceSchedule::withoutGlobalScopes()
        ->where('resource_id', $resource->id)
        ->get();

    expect($schedules->count())->toBe(2);

    // Audit log check
    $audit = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->where('action', 'resource.created')
        ->where('entity_id', $resource->id)
        ->first();

    expect($audit)->not->toBeNull();
});

test('plan limit enforcer blocks creating resource beyond subscription quota', function () {
    // Basic plan has max_resources = 3
    $basicPlan = Plan::where('code', 'BASIC')->first();
    $limits = $basicPlan->limits;
    $limits['max_resources'] = 2;
    $basicPlan->limits = $limits;
    $basicPlan->save();

    Subscription::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->update(['plan_id' => $basicPlan->id]);

    // Create 2 resources
    Resource::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
    ]);

    // Attempt to create 3rd resource
    $payload = [
        'name' => 'Therapist Exceeding Quota',
        'resource_type_id' => $this->staffType->id,
        'capacity' => 1,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ];

    $response = $this->actingAs($this->user)
        ->post(route('owner.resources.store'), $payload);

    $response->assertSessionHas('error');

    $count = Resource::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereNull('archived_at')
        ->count();

    expect($count)->toBe(2);
});

test('archiving a resource frees up subscription quota slot', function () {
    $basicPlan = Plan::where('code', 'BASIC')->first();
    $limits = $basicPlan->limits;
    $limits['max_resources'] = 1;
    $basicPlan->limits = $limits;
    $basicPlan->save();

    Subscription::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->update(['plan_id' => $basicPlan->id]);

    $res1 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
    ]);

    // Quota is 1/1, creating another resource is blocked
    $payload = [
        'name' => 'Second Resource',
        'resource_type_id' => $this->staffType->id,
        'capacity' => 1,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ];

    $this->actingAs($this->user)
        ->post(route('owner.resources.store'), $payload)
        ->assertSessionHas('error');

    // Archive first resource
    $this->actingAs($this->user)
        ->post(route('owner.resources.archive', $res1->id))
        ->assertSessionHas('success');

    expect($res1->fresh()->archived_at)->not->toBeNull();

    // Now creating 2nd resource succeeds because quota is freed!
    $this->actingAs($this->user)
        ->post(route('owner.resources.store'), $payload)
        ->assertSessionHas('success');

    $count = Resource::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereNull('archived_at')
        ->count();

    expect($count)->toBe(1);
});

test('owner can update resource details and weekly schedule', function () {
    $resource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Original Name',
        'capacity' => 1,
    ]);

    $updatePayload = [
        'name' => 'Updated Name Therapist',
        'code' => 'TH-NEW',
        'resource_type_id' => $this->staffType->id,
        'capacity' => 2,
        'visibility' => 'INTERNAL',
        'state' => 'MAINTENANCE',
        'skills' => ['Facial'],
        'schedules' => [
            [
                'day_of_week' => 1,
                'is_available' => true,
                'start_time' => '10:00',
                'end_time' => '19:00',
                'breaks' => [],
            ],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->put(route('owner.resources.update', $resource->id), $updatePayload);

    $response->assertRedirect(route('owner.resources.index'));

    $fresh = $resource->fresh();
    expect($fresh->name)->toBe('Updated Name Therapist');
    expect($fresh->code)->toBe('TH-NEW');
    expect($fresh->capacity)->toBe(2);
    expect($fresh->visibility)->toBe('INTERNAL');
    expect($fresh->state)->toBe('MAINTENANCE');
    expect($fresh->skills)->toBe(['Facial']);
});

test('tenant isolation prevents accessing or modifying other tenant resources', function () {
    $envB = $this->createTenantEnvironment('Other Salon B');
    $tenantB = $envB['tenant'];

    $resourceB = Resource::factory()->create([
        'tenant_id' => $tenantB->id,
        'business_id' => $envB['business']->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Exclusive Therapist B',
    ]);

    // User A attempts to edit Tenant B's resource -> 404
    $this->actingAs($this->user)
        ->get(route('owner.resources.edit', $resourceB->id))
        ->assertStatus(404);

    // User A attempts to update Tenant B's resource -> 404
    $this->actingAs($this->user)
        ->put(route('owner.resources.update', $resourceB->id), [
            'name' => 'Hacked by Tenant A',
            'resource_type_id' => $this->staffType->id,
            'capacity' => 1,
            'visibility' => 'PUBLIC',
            'state' => 'AVAILABLE',
        ])
        ->assertStatus(404);

    // User A attempts to archive Tenant B's resource -> 404
    $this->actingAs($this->user)
        ->post(route('owner.resources.archive', $resourceB->id))
        ->assertStatus(404);

    // User A attempts to delete Tenant B's resource -> 404
    $this->actingAs($this->user)
        ->delete(route('owner.resources.destroy', $resourceB->id))
        ->assertStatus(404);

    expect($resourceB->fresh()->name)->toBe('Exclusive Therapist B');
});
