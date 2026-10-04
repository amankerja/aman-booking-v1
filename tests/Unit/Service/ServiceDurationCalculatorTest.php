<?php

namespace Tests\Unit\Service;

use App\Domain\Business\Models\Business;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Service\Services\ServiceDurationCalculator;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    TenantContext::clear();
    $this->calculator = new ServiceDurationCalculator;
    $this->tenant = Tenant::factory()->create();
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    TenantContext::setTenant($this->tenant);
});

afterEach(function () {
    TenantContext::clear();
});

test('calculator calculates fixed duration and buffers accurately', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'uuid' => 'srv-calc-1',
        'name' => 'Haircut',
        'slug' => 'haircut',
        'price_idr' => 75000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 45,
        'buffer_before' => 10,
        'buffer_after' => 15,
        'capacity' => 1,
    ]);

    $result = $this->calculator->calculate($service);

    expect($result['service_duration'])->toBe(45)
        ->and($result['addon_duration'])->toBe(0)
        ->and($result['buffer_before'])->toBe(10)
        ->and($result['buffer_after'])->toBe(15)
        ->and($result['total_service_duration'])->toBe(45)
        ->and($result['total_occupied_duration'])->toBe(70) // 45 + 10 + 15
        ->and($result['total_price'])->toBe(75000.0);
});

test('calculator calculates per-quantity duration and pricing', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'uuid' => 'srv-calc-2',
        'name' => 'Carpet Cleaning',
        'slug' => 'carpet-cleaning',
        'price_idr' => 50000,
        'duration_type' => 'PER_QUANTITY',
        'duration_minutes' => 30,
        'duration_rule' => ['minutes_per_unit' => 30, 'unit_name' => 'ruangan'],
        'buffer_before' => 5,
        'buffer_after' => 5,
        'capacity' => 1,
    ]);

    // Calculate for 3 rooms
    $result = $this->calculator->calculate($service, ['quantity' => 3]);

    expect($result['service_duration'])->toBe(90) // 30 * 3
        ->and($result['total_occupied_duration'])->toBe(100) // 90 + 5 + 5
        ->and($result['total_price'])->toBe(150000.0); // 50,000 * 3
});

test('calculator calculates variable duration clamped to min and max', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'uuid' => 'srv-calc-3',
        'name' => 'Tattoo Custom',
        'slug' => 'tattoo-custom',
        'price_idr' => 500000,
        'duration_type' => 'VARIABLE',
        'duration_minutes' => 60,
        'duration_rule' => ['min_minutes' => 60, 'max_minutes' => 240],
        'capacity' => 1,
    ]);

    // Requesting 120 minutes (within 60-240)
    $resWithin = $this->calculator->calculate($service, ['custom_duration_minutes' => 120]);
    expect($resWithin['service_duration'])->toBe(120);

    // Requesting 300 minutes (exceeds max 240) -> clamped to 240
    $resAbove = $this->calculator->calculate($service, ['custom_duration_minutes' => 300]);
    expect($resAbove['service_duration'])->toBe(240);

    // Requesting 30 minutes (below min 60) -> clamped to 60
    $resBelow = $this->calculator->calculate($service, ['custom_duration_minutes' => 30]);
    expect($resBelow['service_duration'])->toBe(60);
});

test('calculator incorporates variant overrides and addon additions', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'uuid' => 'srv-calc-4',
        'name' => 'Pet Grooming',
        'slug' => 'pet-grooming',
        'price_idr' => 100000,
        'duration_type' => 'PER_UNIT_SIZE',
        'duration_minutes' => 60,
        'buffer_before' => 5,
        'buffer_after' => 10,
        'capacity' => 1,
    ]);

    // Large dog variant (120 min, Rp 200.000)
    $variantLarge = ServiceVariant::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'name' => 'Large Dog',
        'price_idr' => 200000,
        'duration_minutes' => 120,
    ]);

    // Addons
    $addonFlea = ServiceAddon::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'name' => 'Flea Treatment',
        'price_idr' => 50000,
        'duration_minutes' => 20,
    ]);

    $addonPerfume = ServiceAddon::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'name' => 'Organic Perfume',
        'price_idr' => 25000,
        'duration_minutes' => 5,
    ]);

    $result = $this->calculator->calculate($service, [
        'variant_id' => $variantLarge->id,
        'addon_ids' => [$addonFlea->id, $addonPerfume->id],
    ]);

    expect($result['service_duration'])->toBe(120) // Large variant duration
        ->and($result['addon_duration'])->toBe(25) // 20 + 5
        ->and($result['total_service_duration'])->toBe(145) // 120 + 25
        ->and($result['total_occupied_duration'])->toBe(160) // 145 + 5 + 10 buffer
        ->and($result['variant_price'])->toBe(200000.0)
        ->and($result['addons_price'])->toBe(75000.0) // 50,000 + 25,000
        ->and($result['total_price'])->toBe(275000.0);
});
