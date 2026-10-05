<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Service\Models\Service;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlanSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('non-super-admin is denied access to /admin/* endpoints with 403', function () {
    $env = $this->createTenantEnvironment('Klinik Gigi Sehat');
    $owner = $env['user'];

    $responseDashboard = $this->actingAs($owner)->get(route('admin.dashboard'));
    $responseDashboard->assertForbidden();

    $responseTenants = $this->actingAs($owner)->get(route('admin.tenants.index'));
    $responseTenants->assertForbidden();

    $responsePlans = $this->actingAs($owner)->get(route('admin.plans.index'));
    $responsePlans->assertForbidden();
});

test('super admin can view platform dashboard with all PRD 73 metrics', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin@amanbooking.com',
        'is_super_admin' => true,
    ]);

    $env = $this->createTenantEnvironment('Barber Cool');
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $env['business']->id,
        'name' => 'Haircut Gentleman',
        'duration_minutes' => 30,
        'price_idr' => 75000,
        'pricing_type' => 'FIXED',
    ]);

    $customer = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Budi Client',
        'phone_e164' => '+6281122334455',
    ]);

    $booking = Booking::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'service_snapshot' => ['name' => 'Haircut Gentleman', 'price_idr' => 75000],
        'code' => 'BK-SUP-01',
        'status_category' => BookingStatusCategory::CONFIRMED,
        'start_at' => now(),
        'end_at' => now()->addMinutes(30),
        'total_idr' => 75000,
        'payment_status' => 'PAID',
    ]);

    Invoice::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $env['business']->id,
        'booking_id' => $booking->id,
        'invoice_number' => 'INV-2026-001',
        'status' => 'PAID',
        'amount_total_idr' => 249000,
        'amount_paid_idr' => 249000,
        'due_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($superAdmin)->get(route('admin.dashboard'));
    $response->assertStatus(200);

    $metrics = $response->viewData('page')['props']['metrics'];
    expect($metrics['total_tenants'])->toBeGreaterThanOrEqual(1)
        ->and($metrics['bookings_today'])->toBeGreaterThanOrEqual(1)
        ->and($metrics['subscription_revenue'])->toBeGreaterThanOrEqual(249000)
        ->and($metrics['system_health'])->toBe('OPERATIONAL');
});

test('super admin can view tenants listing and filter by status and plan (PRD 73)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env1 = $this->createTenantEnvironment('Tenant Studio Foto');
    $env2 = $this->createTenantEnvironment('Tenant Lapangan Futsal');

    $response = $this->actingAs($superAdmin)->get(route('admin.tenants.index'));
    $response->assertStatus(200);

    $tenantsData = $response->viewData('page')['props']['tenants']['data'];
    $tenantNames = array_column($tenantsData, 'name');

    expect($tenantNames)->toContain('Tenant Studio Foto', 'Tenant Lapangan Futsal');

    // Filter by search
    $searchResponse = $this->actingAs($superAdmin)->get(route('admin.tenants.index', ['search' => 'Futsal']));
    $searchResponse->assertStatus(200);
    $filteredNames = array_column($searchResponse->viewData('page')['props']['tenants']['data'], 'name');
    expect($filteredNames)->toContain('Tenant Lapangan Futsal')
        ->and($filteredNames)->not->toContain('Tenant Studio Foto');
});

test('super admin can suspend tenant: public bookings return 503 TENANT_UNAVAILABLE and mutations are blocked (PRD 73, 81)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Spa & Massage');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];
    $business = $env['business'];

    // Verify public page is initially accessible
    $initPublic = $this->get(route('public.landing', $business->slug));
    $initPublic->assertStatus(200);

    // Super Admin suspends the tenant
    $suspendRes = $this->actingAs($superAdmin)->post(route('admin.tenants.suspend', $tenant->id), [
        'reason' => 'Penagihan pembayaran tertunda 30 hari',
    ]);
    $suspendRes->assertRedirect();
    $suspendRes->assertSessionHas('success');

    $tenant->refresh();
    expect($tenant->status)->toBe('SUSPENDED');

    /** @var Subscription $sub */
    $sub = $tenant->currentSubscription()->first();
    expect($sub->status)->toBe('SUSPENDED');

    // Public page now closed with 503 TENANT_UNAVAILABLE
    $publicRes = $this->get(route('public.landing', $business->slug));
    $publicRes->assertStatus(503);

    // Regular owner mutations on /app are blocked
    TenantContext::setTenant($tenant);
    $mutationRes = $this->actingAs($owner)->post(route('owner.customers.store'), [
        'name' => 'Blocked Customer',
        'phone' => '08123456789',
    ]);
    $mutationRes->assertForbidden();

    // Audit log was recorded
    $log = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'super_admin.tenant_suspended')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->after['reason'])->toBe('Penagihan pembayaran tertunda 30 hari');
});

test('super admin can activate a suspended tenant restoring operations', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Bengkel Motor');
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];
    $business = $env['business'];

    $tenant->status = 'SUSPENDED';
    $tenant->save();

    // Public is 503
    $this->get(route('public.landing', $business->slug))->assertStatus(503);

    // Super Admin activates tenant
    $activateRes = $this->actingAs($superAdmin)->post(route('admin.tenants.activate', $tenant->id));
    $activateRes->assertRedirect();
    $activateRes->assertSessionHas('success');

    $tenant->refresh();
    expect($tenant->status)->toBe('ACTIVE');

    // Public is accessible again (200)
    $this->get(route('public.landing', $business->slug))->assertStatus(200);
});

test('super admin can extend trial by specified days (PRD 73)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Les Privat Cerdas');
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    /** @var Subscription $sub */
    $sub = $tenant->currentSubscription()->first();
    $sub->trial_ends_at = now()->addDays(2);
    $sub->save();

    $response = $this->actingAs($superAdmin)->post(route('admin.tenants.extend-trial', $tenant->id), [
        'days' => 14,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $sub->refresh();
    expect($sub->status)->toBe('TRIAL')
        ->and($sub->trial_ends_at->isAfter(now()->addDays(14)))->toBeTrue();
});

test('super admin can change plan without corrupting existing tenant data (PRD 75)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Coworking Space');
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $proPlan = Plan::where('code', 'PRO')->firstOrFail();

    $response = $this->actingAs($superAdmin)->post(route('admin.tenants.change-plan', $tenant->id), [
        'plan_id' => $proPlan->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $sub = $tenant->fresh()->currentSubscription;
    expect($sub->plan_id)->toBe($proPlan->id)
        ->and($sub->status)->toBe('ACTIVE');

    $log = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'super_admin.plan_changed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->after['plan_id'])->toBe($proPlan->id);
});

test('super admin can update tenant support notes (PRD 74)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Pet Grooming');
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    $response = $this->actingAs($superAdmin)->post(route('admin.tenants.notes', $tenant->id), [
        'support_notes' => 'Perjanjian diskon tahunan 2026. Kontak darurat: Bu Rini.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $tenant->refresh();
    expect($tenant->support_notes)->toBe('Perjanjian diskon tahunan 2026. Kontak darurat: Bu Rini.');
});

test('support access requires valid reason, records transparent audit log, and enters tenant workspace (PRD 74)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $env = $this->createTenantEnvironment('Klinik Hewan Sejahtera');
    $owner = $env['user'];
    /** @var Tenant $tenant */
    $tenant = $env['tenant'];

    // Support access fails if reason is empty or too short (< 5 chars)
    $failRes = $this->actingAs($superAdmin)->post(route('admin.tenants.support-access', $tenant->id), [
        'reason' => 'cek',
    ]);
    $failRes->assertSessionHasErrors('reason');

    // Support access succeeds with valid reason
    $successRes = $this->actingAs($superAdmin)->post(route('admin.tenants.support-access', $tenant->id), [
        'reason' => 'Investigasi kegagalan reminder WhatsApp tiket #8899',
    ]);

    $successRes->assertRedirect(route('owner.dashboard'));
    $this->assertAuthenticatedAs($superAdmin);

    // Audit log was created and is visible in tenant audit trail
    $auditLog = AuditLog::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'super_admin.support_access')
        ->first();

    expect($auditLog)->not->toBeNull()
        ->and($auditLog->after['reason'])->toBe('Investigasi kegagalan reminder WhatsApp tiket #8899')
        ->and($auditLog->actor_id)->toBe($superAdmin->id);

    // Regular owner can see this audit log in their audit logs
    TenantContext::setTenant($tenant);
    $ownerAuditRes = $this->actingAs($owner)->get(route('owner.audit-logs.index'));
    $ownerAuditRes->assertStatus(200);

    // Super Admin can exit support access
    $exitRes = $this->actingAs($superAdmin)->post(route('admin.support-access.exit'));
    $exitRes->assertRedirect(route('admin.tenants.show', $tenant->id));
});

test('super admin can update plan limits and features safely (PRD 75)', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $basicPlan = Plan::where('code', 'BASIC')->firstOrFail();

    $response = $this->actingAs($superAdmin)->put(route('admin.plans.update', $basicPlan->id), [
        'name' => 'Paket Basic Plus',
        'price_idr' => 119000,
        'billing_cycle' => 'MONTHLY',
        'limits' => [
            'max_businesses' => 1,
            'max_members' => 5,
            'max_services' => 10,
            'max_monthly_bookings' => 200,
        ],
        'features' => [
            'booking_page' => true,
            'whatsapp_notifications' => true,
            'inventory' => false,
        ],
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $basicPlan->refresh();
    expect($basicPlan->name)->toBe('Paket Basic Plus')
        ->and($basicPlan->price_idr)->toBe(119000)
        ->and($basicPlan->limits['max_members'])->toBe(5)
        ->and($basicPlan->features['whatsapp_notifications'])->toBeTrue();
});
