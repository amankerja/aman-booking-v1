<?php

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access styleguide', function () {
    $response = $this->get('/app/_styleguide');
    $response->assertRedirect('/login');
});

test('authenticated user can view design system styleguide', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $user->id]);
    $business = Business::factory()->create(['tenant_id' => $tenant->id]);

    TenantContext::setTenant($tenant);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/app/_styleguide');

    $response->assertOk();
});

test('public booking page renders with public layout and business data', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $user->id]);
    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => 'bengkel-jaya-abadi',
    ]);

    $response = $this->get('/b/bengkel-jaya-abadi');

    $response->assertOk();
});
