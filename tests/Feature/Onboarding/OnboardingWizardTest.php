<?php

namespace Tests\Feature\Onboarding;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Identity\Models\User;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic resource types
        $types = [
            ['code' => 'STAFF', 'name' => 'Staff', 'is_staff' => true],
            ['code' => 'CHAIR', 'name' => 'Kursi Barber', 'is_space' => true],
            ['code' => 'ROOM', 'name' => 'Ruang Pijat', 'is_space' => true],
            ['code' => 'COURT', 'name' => 'Lapangan Olahraga', 'is_space' => true],
            ['code' => 'VEHICLE', 'name' => 'Armada Kendaraan', 'is_equipment' => true],
        ];

        foreach ($types as $t) {
            ResourceType::firstOrCreate(['code' => $t['code']], array_merge($t, ['is_active' => true]));
        }

        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    /**
     * Helper to create test tenant environment.
     *
     * @return array{user: User, tenant: Tenant, business: Business}
     */
    protected function createTenantOwner(string $name = 'Owner Test', string $businessName = 'Bisnis Test'): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => strtolower(Str::slug($name)).'@example.com',
            'is_super_admin' => false,
        ]);

        $tenant = Tenant::create([
            'name' => $businessName,
            'status' => 'ACTIVE',
            'owner_user_id' => $user->id,
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'slug' => Str::slug($businessName).'-'.strtolower(Str::random(5)),
            'name' => $businessName,
            'timezone' => 'Asia/Jakarta',
            'published_at' => null, // Initial draft state
        ]);

        $plan = Plan::first();
        Subscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        setPermissionsTeamId($tenant->id);
        Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $user->assignRole('Owner');

        BusinessMember::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'preset' => 'OWNER',
        ]);

        return [
            'user' => $user,
            'tenant' => $tenant,
            'business' => $business,
        ];
    }

    public function test_guest_cannot_access_onboarding_wizard(): void
    {
        $response = $this->get(route('owner.onboarding'));
        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_access_onboarding_wizard_with_available_templates(): void
    {
        $setup = $this->createTenantOwner();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->get(route('owner.onboarding'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Owner/Onboarding/Index')
            ->has('templates', 6)
            ->has('business')
            ->where('business.id', $setup['business']->id)
            ->where('business.is_published', false)
            ->where('onboarding.completed', false)
        );
    }

    public function test_step_1_saves_business_profile_and_advances_step(): void
    {
        $setup = $this->createTenantOwner();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.step1'), [
                'name' => 'Barbershop Menteng Jaya',
                'whatsapp' => '081234567890',
                'email' => 'menteng@barbershop.test',
                'address' => 'Jl. Menteng Raya No. 45',
                'timezone' => 'Asia/Jakarta',
                'description' => 'Barbershop pria modern dan berkelas.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['business']->refresh();
        $this->assertEquals('Barbershop Menteng Jaya', $setup['business']->name);
        $this->assertEquals('081234567890', $setup['business']->whatsapp);
        $this->assertEquals('menteng@barbershop.test', $setup['business']->email);
        $this->assertEquals(2, $setup['business']->settings['onboarding']['current_step']);
    }

    public function test_step_2_installs_barbershop_template_as_draft_with_system_template_origin(): void
    {
        $setup = $this->createTenantOwner();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.install-template'), [
                'template_id' => 'barbershop',
                'customization' => [
                    'barber_count' => 3,
                    'chair_count' => 2,
                    'customer_picks_staff' => true,
                    'open_time' => '10:00',
                    'close_time' => '21:00',
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['business']->refresh();

        // 1. Business still in DRAFT mode (published_at is null per PRD 168)
        $this->assertNull($setup['business']->published_at);
        $this->assertEquals('barbershop', $setup['business']->settings['onboarding']['template_id']);
        $this->assertEquals('SYSTEM_TEMPLATE', $setup['business']->settings['onboarding']['origin']);
        $this->assertEquals('1.0.0', $setup['business']->settings['onboarding']['version']);

        // 2. Services generated with SYSTEM_TEMPLATE origin
        $services = Service::where('tenant_id', $setup['tenant']->id)
            ->where('business_id', $setup['business']->id)
            ->get();

        $this->assertCount(4, $services);
        foreach ($services as $srv) {
            $this->assertEquals('SYSTEM_TEMPLATE', $srv->rules['origin'] ?? null);
            $this->assertEquals('barbershop', $srv->rules['template_id'] ?? null);
            $this->assertTrue($srv->is_active);
        }

        // 3. Resources generated with customization count (3 barbers + 2 chairs = 5 resources)
        $resources = Resource::where('tenant_id', $setup['tenant']->id)
            ->where('business_id', $setup['business']->id)
            ->get();

        $this->assertCount(5, $resources);
        foreach ($resources as $res) {
            $this->assertEquals('SYSTEM_TEMPLATE', $res->metadata['origin'] ?? null);
            $this->assertEquals('barbershop', $res->metadata['template_id'] ?? null);
        }

        // 4. Operating hours configured for 7 days
        $hours = BusinessHour::where('tenant_id', $setup['tenant']->id)
            ->where('business_id', $setup['business']->id)
            ->get();
        $this->assertCount(7, $hours);

        // 5. Notification templates seeded
        $notifs = NotificationTemplate::where('tenant_id', $setup['tenant']->id)->get();
        $this->assertGreaterThan(0, $notifs->count());
    }

    public function test_custom_business_template_handles_prd_192_questions(): void
    {
        $setup = $this->createTenantOwner();

        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.install-template'), [
                'template_id' => 'custom',
                'customization' => [
                    'what_is_booked' => 'service',
                    'service_name' => 'Sesi Konsultasi Bisnis 1-on-1',
                    'price_idr' => 250000,
                    'duration_minutes' => 90,
                    'customer_picks_resource' => true,
                    'requires_approval' => false,
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $services = Service::where('tenant_id', $setup['tenant']->id)
            ->where('business_id', $setup['business']->id)
            ->get();

        $this->assertCount(1, $services);
        $this->assertEquals('Sesi Konsultasi Bisnis 1-on-1', $services->first()->name);
        $this->assertEquals(250000, $services->first()->price_idr);
        $this->assertEquals(90, $services->first()->duration_minutes);
    }

    public function test_step_3_and_4_can_update_draft_services_and_resources(): void
    {
        $setup = $this->createTenantOwner();

        // Install barbershop template
        $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.install-template'), ['template_id' => 'barbershop']);

        $service = Service::where('tenant_id', $setup['tenant']->id)->first();
        $resource = Resource::where('tenant_id', $setup['tenant']->id)->first();

        // Step 3: Update Services
        $responseSrv = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.update-services'), [
                'services' => [
                    [
                        'id' => $service->id,
                        'name' => 'Potong Rambut VIP Suite',
                        'price_idr' => 120000,
                        'duration_minutes' => 60,
                        'buffer_after' => 10,
                        'is_active' => true,
                    ],
                ],
            ]);

        $responseSrv->assertRedirect();
        $service->refresh();
        $this->assertEquals('Potong Rambut VIP Suite', $service->name);
        $this->assertEquals(120000, $service->price_idr);

        // Step 4: Update Resources
        $responseRes = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.update-resources'), [
                'resources' => [
                    [
                        'id' => $resource->id,
                        'name' => 'Master Barber Denny',
                        'capacity' => 1,
                    ],
                ],
            ]);

        $responseRes->assertRedirect();
        $resource->refresh();
        $this->assertEquals('Master Barber Denny', $resource->name);
    }

    public function test_step_8_publish_activates_business_and_marks_onboarding_completed(): void
    {
        $setup = $this->createTenantOwner();

        $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.install-template'), ['template_id' => 'barbershop']);

        $setup['business']->refresh();
        $this->assertNull($setup['business']->published_at);

        // Step 8: Publish
        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.publish'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['business']->refresh();
        $this->assertNotNull($setup['business']->published_at);
        $this->assertTrue($setup['business']->settings['onboarding']['completed']);
        $this->assertEquals(8, $setup['business']->settings['onboarding']['current_step']);

        // Now public landing page should be 200 OK!
        $publicRes = $this->get('/'.$setup['business']->slug);
        $publicRes->assertOk();
    }

    public function test_reset_clears_draft_template_data_for_reselection(): void
    {
        $setup = $this->createTenantOwner();

        // Install barbershop
        $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.install-template'), ['template_id' => 'barbershop']);

        $this->assertGreaterThan(0, Service::where('tenant_id', $setup['tenant']->id)->count());

        // Reset template
        $response = $this->actingAs($setup['user'])
            ->withSession(['active_tenant_id' => $setup['tenant']->id])
            ->post(route('owner.onboarding.reset'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // All SYSTEM_TEMPLATE draft services and resources should be removed
        $this->assertEquals(0, Service::where('tenant_id', $setup['tenant']->id)->count());
        $this->assertEquals(0, Resource::where('tenant_id', $setup['tenant']->id)->count());

        $setup['business']->refresh();
        $this->assertEquals(2, $setup['business']->settings['onboarding']['current_step']);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access_during_onboarding(): void
    {
        $tenantA = $this->createTenantOwner('Owner A', 'Bisnis A');
        $tenantB = $this->createTenantOwner('Owner B', 'Bisnis B');

        // Tenant A installs template
        $this->actingAs($tenantA['user'])
            ->withSession(['active_tenant_id' => $tenantA['tenant']->id])
            ->post(route('owner.onboarding.install-template'), ['template_id' => 'barbershop']);

        $serviceA = Service::where('tenant_id', $tenantA['tenant']->id)->first();

        // Tenant B attempts to update Tenant A's service via update-services endpoint
        $this->actingAs($tenantB['user'])
            ->withSession(['active_tenant_id' => $tenantB['tenant']->id])
            ->post(route('owner.onboarding.update-services'), [
                'services' => [
                    [
                        'id' => $serviceA->id,
                        'name' => 'Hacked Service Name',
                        'price_idr' => 1000,
                        'duration_minutes' => 10,
                        'buffer_after' => 0,
                        'is_active' => true,
                    ],
                ],
            ]);

        // Service A remains completely untouched
        $serviceA->refresh();
        $this->assertNotEquals('Hacked Service Name', $serviceA->name);
    }
}
