<?php

namespace Tests\Feature\Auth;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles and permissions for tests
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

test('new owner can register and creates user, tenant, business, and trial subscription', function () {
    $response = $this->post('/register', [
        'name' => 'Faqih Owner',
        'email' => 'faqih@barbershop.test',
        'password' => 'Rahasia123!',
        'password_confirmation' => 'Rahasia123!',
        'business_name' => 'Barbershop Keren',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('owner.dashboard'));

    // 1. Verify User created
    $user = User::where('email', 'faqih@barbershop.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Faqih Owner')
        ->and($user->is_super_admin)->toBeFalse();

    // 2. Verify Tenant created
    $tenant = Tenant::where('owner_user_id', $user->id)->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Barbershop Keren')
        ->and($tenant->status)->toBe('ACTIVE');

    // 3. Verify Business created with unique slug
    $business = Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($business)->not->toBeNull()
        ->and($business->slug)->toBe('barbershop-keren')
        ->and($business->name)->toBe('Barbershop Keren');

    // 4. Verify Trial Subscription created
    $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe('TRIAL')
        ->and($subscription->trial_ends_at)->not->toBeNull();

    // 5. Verify BusinessMember created
    $member = BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
    expect($member)->not->toBeNull()
        ->and($member->user_id)->toBe($user->id)
        ->and($member->preset)->toBe('OWNER');

    // 6. Verify Spatie Role assigned to User for this tenant team
    setPermissionsTeamId($tenant->id);
    expect($user->hasRole('Owner'))->toBeTrue();
});

test('registration handles slug collisions by appending counter', function () {
    // First business with slug "barbershop-keren"
    $this->post('/register', [
        'name' => 'Owner 1',
        'email' => 'owner1@test.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'business_name' => 'Barbershop Keren',
    ]);

    auth()->logout();

    // Second business with same name
    $this->post('/register', [
        'name' => 'Owner 2',
        'email' => 'owner2@test.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'business_name' => 'Barbershop Keren',
    ]);

    $businesses = Business::withoutGlobalScopes()->where('name', 'Barbershop Keren')->get();
    expect($businesses)->toHaveCount(2);

    $slugs = $businesses->pluck('slug')->all();
    expect($slugs)->toContain('barbershop-keren')
        ->and($slugs)->toContain('barbershop-keren-2');
});

test('registration requires valid input', function () {
    $response = $this->post('/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
        'business_name' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password', 'business_name']);
    $this->assertGuest();
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post('/register', [
        'name' => 'Another User',
        'email' => 'existing@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'business_name' => 'Another Business',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});
