<?php

namespace Tests\Feature\Service;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
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

test('owner can view services index page with categories and plan usage', function () {
    $env = $this->createTenantEnvironment('Barber Shop');

    $category = ServiceCategory::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'name' => 'Haircut',
        'slug' => 'haircut',
    ]);

    Service::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'category_id' => $category->id,
        'uuid' => 'srv-1111',
        'name' => 'Signature Haircut',
        'slug' => 'signature-haircut',
        'price_idr' => 75000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 45,
        'buffer_before' => 5,
        'buffer_after' => 10,
        'capacity' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($env['user'])->get(route('owner.services.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Services/Index')
        ->has('services', 1)
        ->where('services.0.name', 'Signature Haircut')
        ->where('services.0.price_idr', 75000)
        ->where('services.0.total_duration', 60) // 45 + 5 + 10
        ->has('categories', 1)
        ->has('usage')
    );
});

test('owner can create service in basic mode', function () {
    $env = $this->createTenantEnvironment('Salon Cantik');

    $response = $this->actingAs($env['user'])->post(route('owner.services.store'), [
        'name' => 'Cuci Blow Premium',
        'price_idr' => 120000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 45,
        'capacity' => 1,
        'is_active' => true,
        'description' => 'Cuci rambut dengan vitamin dan styling blow dry.',
    ]);

    $response->assertRedirect(route('owner.services.index'));
    $response->assertSessionHas('success');

    $service = Service::where('business_id', $env['business']->id)
        ->where('name', 'Cuci Blow Premium')
        ->first();

    expect($service)->not->toBeNull()
        ->and($service->price_idr)->toBe('120000.00')
        ->and($service->duration_minutes)->toBe(45)
        ->and($service->capacity)->toBe(1)
        ->and($service->is_active)->toBeTrue();

    // Verify Audit log
    $audit = AuditLog::where('action', 'service.created')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->tenant_id)->toBe($env['tenant']->id)
        ->and($audit->entity_id)->toBe((string) $service->id);
});

test('owner can create service with variants and addons', function () {
    $env = $this->createTenantEnvironment('Auto Detailing');

    $response = $this->actingAs($env['user'])->post(route('owner.services.store'), [
        'name' => 'Car Wash Detailing',
        'price_idr' => 100000,
        'duration_type' => 'PER_UNIT_SIZE',
        'duration_minutes' => 60,
        'capacity' => 1,
        'buffer_before' => 10,
        'buffer_after' => 15,
        'is_active' => true,
        'variants' => [
            ['name' => 'City Car / Sedang', 'price_idr' => 100000, 'duration_minutes' => 60],
            ['name' => 'Large SUV / MPV', 'price_idr' => 150000, 'duration_minutes' => 90],
        ],
        'addons' => [
            ['name' => 'Fogging Anti Bakteri', 'price_idr' => 50000, 'duration_minutes' => 15],
            ['name' => 'Waxing Body', 'price_idr' => 75000, 'duration_minutes' => 30],
        ],
    ]);

    $response->assertRedirect(route('owner.services.index'));
    $response->assertSessionHas('success');

    $service = Service::where('business_id', $env['business']->id)->first();
    expect($service)->not->toBeNull()
        ->and($service->variants)->toHaveCount(2)
        ->and($service->addons)->toHaveCount(2)
        ->and($service->variants[1]->name)->toBe('Large SUV / MPV')
        ->and($service->variants[1]->duration_minutes)->toBe(90)
        ->and($service->addons[0]->name)->toBe('Fogging Anti Bakteri');
});

test('plan limit blocks service creation when quota exceeded', function () {
    $env = $this->createTenantEnvironment('Budget Salon');

    // Create plan with max_services = 2
    $limitedPlan = Plan::factory()->create([
        'code' => 'STARTER_LIMIT_2',
        'limits' => [
            'max_businesses' => 1,
            'max_members' => 2,
            'max_services' => 2,
            'max_monthly_bookings' => 50,
        ],
    ]);

    $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $env['tenant']->id)->first();
    $subscription->update(['plan_id' => $limitedPlan->id]);

    // Create 2 services to reach limit
    for ($i = 1; $i <= 2; $i++) {
        Service::withoutGlobalScopes()->create([
            'tenant_id' => $env['tenant']->id,
            'business_id' => $env['business']->id,
            'uuid' => "srv-lim-{$i}",
            'name' => "Service {$i}",
            'slug' => "service-{$i}",
            'price_idr' => 50000,
            'duration_type' => 'FIXED',
            'duration_minutes' => 30,
            'capacity' => 1,
            'is_active' => true,
        ]);
    }

    // 3rd service creation attempt must fail with plan limit error
    $response = $this->actingAs($env['user'])->post(route('owner.services.store'), [
        'name' => 'Service 3 Exceeded',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 30,
        'capacity' => 1,
    ]);

    $response->assertSessionHasErrors(['plan_limit']);
    expect(Service::where('business_id', $env['business']->id)->count())->toBe(2);
});

test('archiving a service frees up plan quota slot', function () {
    $env = $this->createTenantEnvironment('Spa Alami');

    $limitedPlan = Plan::factory()->create([
        'code' => 'MINI_SPA',
        'limits' => ['max_services' => 1],
    ]);

    $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $env['tenant']->id)->first();
    $subscription->update(['plan_id' => $limitedPlan->id]);

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'uuid' => 'srv-spa-1',
        'name' => 'Lulur Bali Tradisional',
        'slug' => 'lulur-bali',
        'price_idr' => 150000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 90,
        'capacity' => 1,
        'is_active' => true,
    ]);

    // Archive the service
    $responseArchive = $this->actingAs($env['user'])->post(route('owner.services.archive', $service->id));
    $responseArchive->assertSessionHas('success');

    $service->refresh();
    expect($service->is_archived)->toBeTrue();

    // Now owner can create a new service because archived service does not consume active quota
    $responseNew = $this->actingAs($env['user'])->post(route('owner.services.store'), [
        'name' => 'Aromatherapy Massage',
        'price_idr' => 180000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'capacity' => 1,
    ]);

    $responseNew->assertRedirect(route('owner.services.index'));
    $responseNew->assertSessionHas('success');
    expect(Service::where('business_id', $env['business']->id)->whereNull('archived_at')->count())->toBe(1);
});

test('owner can update service and sync variants and addons', function () {
    $env = $this->createTenantEnvironment('Dental Hub');

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'uuid' => 'srv-dent-1',
        'name' => 'Pembersihan Karang Gigi (Scaling)',
        'slug' => 'scaling-gigi',
        'price_idr' => 250000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 45,
        'capacity' => 1,
    ]);

    $oldVariant = ServiceVariant::create([
        'tenant_id' => $env['tenant']->id,
        'service_id' => $service->id,
        'name' => 'Dewasa',
        'price_idr' => 250000,
        'duration_minutes' => 45,
    ]);

    $response = $this->actingAs($env['user'])->put(route('owner.services.update', $service->id), [
        'name' => 'Scaling Gigi & Polishing',
        'price_idr' => 300000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'capacity' => 1,
        'buffer_before' => 5,
        'buffer_after' => 15,
        'variants' => [
            ['id' => $oldVariant->id, 'name' => 'Dewasa Reguler', 'price_idr' => 300000, 'duration_minutes' => 60],
            ['name' => 'Anak-Anak', 'price_idr' => 200000, 'duration_minutes' => 30],
        ],
        'addons' => [
            ['name' => 'Aplikasi Fluoride', 'price_idr' => 100000, 'duration_minutes' => 10],
        ],
    ]);

    $response->assertRedirect(route('owner.services.index'));
    $response->assertSessionHas('success');

    $service->refresh();
    expect($service->name)->toBe('Scaling Gigi & Polishing')
        ->and($service->price_idr)->toBe('300000.00')
        ->and($service->duration_minutes)->toBe(60)
        ->and($service->variants)->toHaveCount(2)
        ->and($service->variants[0]->name)->toBe('Dewasa Reguler')
        ->and($service->addons)->toHaveCount(1);
});

test('owner can delete service when it has no bookings', function () {
    $env = $this->createTenantEnvironment('Studio Musik');

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'uuid' => 'srv-jam-1',
        'name' => 'Sewa Studio Jamming',
        'slug' => 'sewa-studio',
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'capacity' => 1,
    ]);

    $response = $this->actingAs($env['user'])->delete(route('owner.services.destroy', $service->id));

    $response->assertRedirect(route('owner.services.index'));
    $response->assertSessionHas('success');

    expect(Service::find($service->id))->toBeNull();

    // Verify Audit log
    $audit = AuditLog::where('action', 'service.deleted')->latest()->first();
    expect($audit)->not->toBeNull()
        ->and($audit->tenant_id)->toBe($env['tenant']->id);
});

test('tenant isolation prevents accessing or modifying other tenant services', function () {
    $envA = $this->createTenantEnvironment('Tenant Studio A');
    $envB = $this->createTenantEnvironment('Tenant Studio B');

    $serviceB = Service::withoutGlobalScopes()->create([
        'tenant_id' => $envB['tenant']->id,
        'business_id' => $envB['business']->id,
        'uuid' => 'srv-b-1',
        'name' => 'Layanan Rahasia B',
        'slug' => 'layanan-rahasia-b',
        'price_idr' => 500000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
        'capacity' => 1,
    ]);

    // User A cannot view edit form of Service B
    $responseEdit = $this->actingAs($envA['user'])->get(route('owner.services.edit', $serviceB->id));
    $responseEdit->assertStatus(404);

    // User A cannot update Service B
    $responseUpdate = $this->actingAs($envA['user'])->put(route('owner.services.update', $serviceB->id), [
        'name' => 'Hacked by A',
        'price_idr' => 1000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 10,
        'capacity' => 1,
    ]);
    $responseUpdate->assertStatus(404);

    // User A cannot delete Service B
    $responseDelete = $this->actingAs($envA['user'])->delete(route('owner.services.destroy', $serviceB->id));
    $responseDelete->assertStatus(404);

    // Service B is safe
    expect(Service::withoutGlobalScopes()->find($serviceB->id))->not->toBeNull()
        ->and(Service::withoutGlobalScopes()->find($serviceB->id)->name)->toBe('Layanan Rahasia B');
});
