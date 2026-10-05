<?php

namespace Tests\Feature\Gate;

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase2GateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        ResourceType::firstOrCreate(
            ['code' => 'STAFF'],
            ['name' => 'Barber', 'icon' => 'User', 'is_staff' => true, 'is_active' => true]
        );
    }

    /**
     * Helper to create live published business for Gate verification.
     *
     * @return array{business: Business, service: Service, staff: resource, tenant: Tenant}
     */
    protected function createLiveBusiness(): array
    {
        $user = User::factory()->create([
            'name' => 'Owner Gate Verification',
            'email' => 'gate_owner_live@test.com',
        ]);

        $tenant = Tenant::create([
            'name' => 'Barbershop Gate Verification',
            'status' => 'ACTIVE',
            'owner_user_id' => $user->id,
        ]);

        $business = Business::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Barbershop Gate Mobile',
            'slug' => 'barbershop-gate-mobile',
            'timezone' => 'Asia/Jakarta',
            'whatsapp' => '081234567890',
            'email' => 'gate@barbershop.test',
            'address' => 'Jl. Gatot Subroto No. 88, Jakarta Selatan',
            'description' => 'Barbershop premium untuk verifikasi gate phase 2.',
            'published_at' => now(), // Live
            'settings' => [
                'onboarding' => [
                    'completed' => true,
                    'current_step' => 8,
                    'template_id' => 'barbershop',
                ],
            ],
        ]);

        $plan = Plan::first();
        Subscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'ACTIVE',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // 7 days operating hours 09:00 - 21:00
        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'day_of_week' => $d,
                'is_open' => true,
                'open_time' => '09:00:00',
                'close_time' => '21:00:00',
            ]);
        }

        $category = ServiceCategory::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'name' => 'Haircut',
            'slug' => 'haircut',
        ]);

        $service = Service::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'category_id' => $category->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Executive Haircut & Shave',
            'slug' => 'executive-haircut-shave',
            'price_idr' => 75000,
            'duration_type' => 'FIXED',
            'duration_minutes' => 45,
            'buffer_after' => 5,
            'capacity' => 1,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $staffType = ResourceType::where('code', 'STAFF')->first();
        $staff = Resource::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'resource_type_id' => $staffType->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Barber Rizky',
            'capacity' => 1,
            'visibility' => 'PUBLIC',
            'state' => 'AVAILABLE',
        ]);

        ServiceResourceRule::create([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'resource_type_id' => $staffType->id,
            'resource_id' => $staff->id,
            'is_required' => true,
            'assignment_mode' => ServiceResourceRule::MODE_CUSTOMER_CHOICE,
            'quantity' => 1,
        ]);

        return [
            'tenant' => $tenant,
            'business' => $business,
            'service' => $service,
            'staff' => $staff,
        ];
    }

    /**
     * EXIT CRITERIA 1:
     * Dari HP: buka link -> booking selesai < 60 detik (E2E full transaction & latency).
     */
    public function test_gate_exit_criteria_1_mobile_booking_flow_under_60_seconds(): void
    {
        $live = $this->createLiveBusiness();
        $slug = $live['business']->slug;
        $service = $live['service'];
        $staff = $live['staff'];

        $startTime = microtime(true);

        // Step 1: Open landing page on mobile browser
        $landingRes = $this->get('/'.$slug);
        $landingRes->assertOk();
        $this->assertLessThan(100 * 1024, strlen($landingRes->getContent()));

        // Step 2: Open customer booking interface
        $bookingPageRes = $this->get('/'.$slug.'/booking');
        $bookingPageRes->assertOk();

        // Step 3: Fetch real-time availability slots
        $targetDate = Carbon::tomorrow()->format('Y-m-d');
        $availRes = $this->getJson("/{$slug}/availability?date={$targetDate}&service_id={$service->id}&staff_id={$staff->id}");
        $availRes->assertOk();
        $availData = $availRes->json();
        $this->assertNotEmpty($availData['slots']);

        $slot = $availData['slots'][0];
        $startAt = $slot['start_at'];

        // Step 4: Submit booking reservation with idempotency key
        $idempotencyKey = (string) Str::uuid();
        $bookingSubmitRes = $this->postJson('/'.$slug.'/booking', [
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'start_at' => $startAt,
            'customer_name' => 'Budi Setiawan',
            'customer_phone' => '081234567890',
            'customer_email' => 'budi@gmail.test',
            'customer_notes' => 'Tolong potongan undercut rapi.',
            'idempotency_key' => $idempotencyKey,
        ]);

        $bookingSubmitRes->assertStatus(200);
        $submitData = $bookingSubmitRes->json();
        $this->assertTrue($submitData['success']);
        $this->assertNotEmpty($submitData['code']);
        $this->assertNotEmpty($submitData['manage_token']);

        $bookingCode = $submitData['code'];
        $manageToken = $submitData['manage_token'];

        // Step 5: Render confirmation & success page
        $successPageRes = $this->get('/'.$slug.'/booking/success/'.$bookingCode);
        $successPageRes->assertOk();

        // Step 6: Export calendar .ics
        $calendarRes = $this->get('/'.$slug.'/booking/success/'.$bookingCode.'/calendar.ics');
        $calendarRes->assertOk();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $calendarRes->getContent());

        // Step 7: Access customer self-service management portal
        $manageRes = $this->get('/'.$slug.'/booking/manage/'.$manageToken);
        $manageRes->assertOk();

        $elapsedSeconds = microtime(true) - $startTime;

        // Total backend processing time across all 7 steps must be well under 5 seconds
        // (Leaving > 55 seconds for user tactile interaction on mobile to comfortably meet < 60s target)
        $this->assertLessThan(5.0, $elapsedSeconds, "Backend mobile transaction flow took {$elapsedSeconds}s, target is < 5s.");

        // Verify booking in database
        $savedBooking = Booking::withoutGlobalScopes()->where('code', $bookingCode)->first();
        $this->assertNotNull($savedBooking);
        $this->assertEquals('CONFIRMED', $savedBooking->status_category->value);
        $this->assertEquals($service->price_idr, $savedBooking->total_idr);
    }

    /**
     * EXIT CRITERIA 2:
     * Performance Budget (PRD 204.8):
     * Landing HTML < 100 KB, 0 render-blocking JS, .htaccess caching headers, availability p95 < 500 ms.
     */
    public function test_gate_exit_criteria_2_performance_budget_and_caching_directives(): void
    {
        $live = $this->createLiveBusiness();
        $slug = $live['business']->slug;

        // 1. Landing HTML size & zero render-blocking JS
        $landingRes = $this->get('/'.$slug);
        $landingRes->assertOk();
        $html = $landingRes->getContent();

        $this->assertLessThan(100 * 1024, strlen($html), 'Landing HTML must be strictly under 100 KB');
        $this->assertEquals(0, preg_match_all('/<script\b(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html));

        // 2. Availability latency < 500 ms
        $start = microtime(true);
        $date = Carbon::tomorrow()->format('Y-m-d');
        $res = $this->getJson("/api/public/{$slug}/availability?date={$date}&service_id={$live['service']->id}");
        $res->assertOk();
        $latencyMs = (microtime(true) - $start) * 1000;
        $this->assertLessThan(500, $latencyMs, 'Availability endpoint must respond under 500 ms');

        // 3. .htaccess directives exist
        $htaccessPath = public_path('.htaccess');
        $this->assertFileExists($htaccessPath);
        $htaccessContent = file_get_contents($htaccessPath);

        $this->assertStringContainsString('mod_deflate.c', $htaccessContent);
        $this->assertStringContainsString('mod_expires.c', $htaccessContent);
        $this->assertStringContainsString('max-age=31536000, immutable', $htaccessContent);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $htaccessContent);
    }

    /**
     * EXIT CRITERIA 3:
     * Owner Baru Publish < 15 menit (PRD 48, 168):
     * Wizard 8 langkah, preset templates, safe draft installation, publish to live.
     */
    public function test_gate_exit_criteria_3_new_owner_onboarding_and_publish(): void
    {
        // 1. Register owner
        $user = User::factory()->create(['name' => 'Owner Gate Test', 'email' => 'gate_owner@test.com']);
        $tenant = Tenant::create(['name' => 'Gate Barbershop', 'status' => 'ACTIVE', 'owner_user_id' => $user->id]);
        $business = Business::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gate Barbershop',
            'slug' => 'gate-barbershop-live',
            'timezone' => 'Asia/Jakarta',
            'published_at' => null, // Draft
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
        $user->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));

        // Step 1: Save Profile
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('owner.onboarding.step1'), [
                'name' => 'Gate Barbershop Sukses',
                'whatsapp' => '081234567890',
                'timezone' => 'Asia/Jakarta',
            ])->assertRedirect();

        // Step 2: Install Barbershop Template (SYSTEM_TEMPLATE)
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('owner.onboarding.install-template'), ['template_id' => 'barbershop'])
            ->assertRedirect();

        $business->refresh();
        $this->assertNull($business->published_at);
        $this->assertEquals('SYSTEM_TEMPLATE', $business->settings['onboarding']['origin']);

        // Step 8: Publish
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id])
            ->post(route('owner.onboarding.publish'))
            ->assertRedirect();

        $business->refresh();
        $this->assertNotNull($business->published_at);
        $this->assertTrue($business->settings['onboarding']['completed']);

        // Verify public route is now 200 OK!
        $publicLanding = $this->get('/'.$business->slug);
        $publicLanding->assertOk();
    }
}
