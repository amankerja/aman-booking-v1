<?php

namespace Tests\Feature\Reporting;

use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Reporting\Services\ReportService;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('summary metrics accurately calculate bookings, revenue, and customer counts (PRD 44)', function () {
    $env = $this->createTenantEnvironment('Klinik Sehat');
    $tenant = $env['tenant'];

    $customer1 = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Budi Baru']);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Siti Repeat']);
    $customer3 = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Joko Batal']);

    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Konsultasi Dokter']);

    $now = Carbon::parse('2026-10-15 10:00:00');
    Carbon::setTestNow($now);

    // Prior booking for Customer 2 (40 days ago, making Customer 2 a repeat customer in the test period)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer2->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 100000,
        'start_at' => $now->copy()->subDays(40),
        'end_at' => $now->copy()->subDays(40)->addHour(),
    ]);

    // Bookings within the 30-day filter window:
    // 1. Customer 1 (New): COMPLETED, total 150.000
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer1->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 150000,
        'start_at' => $now->copy()->subDays(5),
        'end_at' => $now->copy()->subDays(5)->addHour(),
    ]);

    // 2. Customer 2 (Repeat): CONFIRMED, PAID, total 200.000
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer2->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'PAID',
        'total_idr' => 200000,
        'start_at' => $now->copy()->subDays(3),
        'end_at' => $now->copy()->subDays(3)->addHour(),
    ]);

    // 3. Customer 3 (New): CANCELLED, UNPAID, total 75.000 (not counted in revenue)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer3->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CANCELLED,
        'payment_status' => 'UNPAID',
        'total_idr' => 75000,
        'start_at' => $now->copy()->subDays(2),
        'end_at' => $now->copy()->subDays(2)->addHour(),
    ]);

    // 4. Customer 1: PENDING, UNPAID, total 50.000
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer1->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'payment_status' => 'UNPAID',
        'total_idr' => 50000,
        'start_at' => $now->copy()->subDays(1),
        'end_at' => $now->copy()->subDays(1)->addHour(),
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $filters = ['preset' => '30d'];

    $summary = $reportService->getSummary($tenant, $filters);

    expect($summary['total_bookings'])->toBe(4)
        ->and($summary['completed_bookings'])->toBe(1)
        ->and($summary['confirmed_bookings'])->toBe(1)
        ->and($summary['pending_bookings'])->toBe(1)
        ->and($summary['cancelled_bookings'])->toBe(1)
        ->and($summary['total_revenue'])->toBe(350000) // 150.000 + 200.000
        ->and($summary['new_customers_count'])->toBe(2) // Customer 1 & Customer 3
        ->and($summary['repeat_customers_count'])->toBe(1); // Customer 2

    Carbon::setTestNow();
});

test('resource utilization calculates PRD 162 formula accurately: (Booked / Available) * 100%', function () {
    $env = $this->createTenantEnvironment('Barber Studio');
    $tenant = $env['tenant'];

    // Monday, Oct 12, 2026 (day of week = 1)
    $monday = Carbon::parse('2026-10-12 09:00:00');

    $resType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'BARBER',
        'name' => 'Barber',
        'icon' => 'user',
        'is_staff' => true,
        'is_active' => true,
    ]);
    $resource = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $resType->id,
        'name' => 'Ahmad Barber',
    ]);

    // Ahmad's schedule on Monday: 09:00 - 17:00 (480 mins) with 1 hour break 12:00 - 13:00 (60 mins)
    // Available = 420 mins
    ResourceSchedule::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $resource->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00',
        'end_time' => '17:00',
        'breaks' => [
            ['start' => '12:00', 'end' => '13:00', 'title' => 'Istirahat Siang'],
        ],
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    // Booking 1: 09:30 - 11:15 = 105 mins booked
    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'start_at' => $monday->copy()->setTime(9, 30),
        'end_at' => $monday->copy()->setTime(11, 15),
    ]);

    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'resource_id' => $resource->id,
        'role' => 'PRIMARY',
        'start_at' => $monday->copy()->setTime(9, 30),
        'end_at' => $monday->copy()->setTime(11, 15),
        'status' => AllocationStatus::ACTIVE,
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $filters = [
        'preset' => 'custom',
        'date_from' => '2026-10-12',
        'date_to' => '2026-10-12',
    ];

    $resources = $reportService->getResourceUtilization($tenant, $filters);

    expect($resources)->toHaveCount(1);
    $item = $resources[0];

    // 105 mins / 420 mins = 25.0%
    expect($item['booked_minutes'])->toBe(105)
        ->and($item['available_minutes'])->toBe(420)
        ->and($item['utilization_rate'])->toBe(25.0);
});

test('resource utilization respects business hours fallback and time block leaves', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $monday = Carbon::parse('2026-10-12 08:00:00');

    $resType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'DENTIST',
        'name' => 'Dokter Gigi',
        'icon' => 'user',
        'is_staff' => true,
        'is_active' => true,
    ]);
    $resource = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $resType->id,
        'name' => 'drg. Maya',
    ]);

    // No ResourceSchedule, fallback to BusinessHour on Monday: 08:00 - 16:00 (480 mins)
    BusinessHour::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'day_of_week' => 1,
        'is_open' => true,
        'open_time' => '08:00',
        'close_time' => '16:00',
        'breaks' => [],
    ]);

    // TimeBlock (Leave/Seminar) for drg. Maya on Monday 10:00 - 12:00 (120 mins)
    TimeBlock::create([
        'tenant_id' => $tenant->id,
        'resource_id' => $resource->id,
        'start_at' => $monday->copy()->setTime(10, 0),
        'end_at' => $monday->copy()->setTime(12, 0),
        'reason' => 'Seminar Kedokteran',
        'is_all_resources' => false,
    ]);

    // Net available working time = 480 - 120 = 360 mins.
    // Booking: 90 mins (13:00 - 14:30)
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'start_at' => $monday->copy()->setTime(13, 0),
        'end_at' => $monday->copy()->setTime(14, 30),
    ]);

    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'resource_id' => $resource->id,
        'role' => 'PRIMARY',
        'start_at' => $monday->copy()->setTime(13, 0),
        'end_at' => $monday->copy()->setTime(14, 30),
        'status' => AllocationStatus::ACTIVE,
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $filters = [
        'preset' => 'custom',
        'date_from' => '2026-10-12',
        'date_to' => '2026-10-12',
    ];

    $resources = $reportService->getResourceUtilization($tenant, $filters);
    expect($resources)->toHaveCount(1);
    $item = $resources[0];

    // 90 mins / 360 mins = 25.0%
    expect($item['available_minutes'])->toBe(360)
        ->and($item['booked_minutes'])->toBe(90)
        ->and($item['utilization_rate'])->toBe(25.0);
});

test('service metrics accurately calculate PRD 163 top services, cancellation rate, and revenue', function () {
    $env = $this->createTenantEnvironment('Spa & Reflexology');
    $tenant = $env['tenant'];
    $business = $env['business'];

    $category = ServiceCategory::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Massage',
        'slug' => 'massage',
    ]);

    $service1 = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'name' => 'Aromatherapy Massage',
    ]);

    $service2 = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'name' => 'Foot Reflexology',
    ]);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $date = Carbon::parse('2026-10-10 10:00:00');

    // Service 1: 2 completed bookings (100k + 150k) and 1 cancelled booking
    // Total = 3, completed = 2, cancelled = 1, cancellation_rate = 33.3%, revenue = 250.000
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service1->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 100000,
        'start_at' => $date->copy(),
        'end_at' => $date->copy()->addHour(),
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service1->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 150000,
        'start_at' => $date->copy()->addHours(2),
        'end_at' => $date->copy()->addHours(3),
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service1->id,
        'status_category' => BookingStatusCategory::CANCELLED,
        'payment_status' => 'UNPAID',
        'total_idr' => 100000,
        'start_at' => $date->copy()->addHours(4),
        'end_at' => $date->copy()->addHours(5),
    ]);

    // Service 2: 1 confirmed & paid booking (80k)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service2->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'payment_status' => 'PAID',
        'total_idr' => 80000,
        'start_at' => $date->copy(),
        'end_at' => $date->copy()->addHour(),
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $filters = [
        'preset' => 'custom',
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-10',
    ];

    $metrics = $reportService->getServiceMetrics($tenant, $filters);

    expect($metrics)->toHaveCount(2);

    $s1 = collect($metrics)->firstWhere('id', $service1->id);
    expect($s1['total_bookings'])->toBe(3)
        ->and($s1['completed_bookings'])->toBe(2)
        ->and($s1['cancelled_bookings'])->toBe(1)
        ->and($s1['cancellation_rate'])->toBe(33.3)
        ->and($s1['total_revenue'])->toBe(250000);

    $s2 = collect($metrics)->firstWhere('id', $service2->id);
    expect($s2['total_bookings'])->toBe(1)
        ->and($s2['completed_bookings'])->toBe(0)
        ->and($s2['cancelled_bookings'])->toBe(0)
        ->and($s2['cancellation_rate'])->toBe(0.0)
        ->and($s2['total_revenue'])->toBe(80000);
});

test('strict multi-tenant isolation: Tenant A never aggregates Tenant B data or revenue', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $envB = $this->createTenantEnvironment('Tenant Beta');

    $tenantA = $envA['tenant'];
    $tenantB = $envB['tenant'];

    $serviceA = Service::factory()->create(['tenant_id' => $tenantA->id]);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

    $custA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
    $custB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

    $date = Carbon::parse('2026-10-10 10:00:00');

    // Tenant A: 1 completed booking, 100.000
    Booking::factory()->create([
        'tenant_id' => $tenantA->id,
        'customer_id' => $custA->id,
        'service_id' => $serviceA->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 100000,
        'start_at' => $date,
        'end_at' => $date->copy()->addHour(),
    ]);

    // Tenant B: 2 completed bookings, 500.000 each = 1.000.000
    Booking::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $custB->id,
        'service_id' => $serviceB->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 500000,
        'start_at' => $date,
        'end_at' => $date->copy()->addHour(),
    ]);
    Booking::factory()->create([
        'tenant_id' => $tenantB->id,
        'customer_id' => $custB->id,
        'service_id' => $serviceB->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 500000,
        'start_at' => $date->copy()->addHours(2),
        'end_at' => $date->copy()->addHours(3),
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $filters = [
        'preset' => 'custom',
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-10',
    ];

    $summaryA = $reportService->getSummary($tenantA, $filters);
    $summaryB = $reportService->getSummary($tenantB, $filters);

    expect($summaryA['total_bookings'])->toBe(1)
        ->and($summaryA['total_revenue'])->toBe(100000)
        ->and($summaryB['total_bookings'])->toBe(2)
        ->and($summaryB['total_revenue'])->toBe(1000000);
});

test('owner can access /app/reports dashboard endpoint and view analytics', function () {
    $env = $this->createTenantEnvironment('Yoga Studio');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->get(route('owner.reports.index'));

    $response->assertStatus(200);
    $response->assertInertia(function ($page) {
        $page->component('Owner/Reports/Index')
            ->has('summary')
            ->has('resources')
            ->has('services')
            ->has('daily_trends')
            ->has('filters')
            ->has('filter_options')
            ->has('can_export');
    });
});

test('CSV export endpoint streams valid CSV with correct headers and data', function () {
    $env = $this->createTenantEnvironment('Beauty Center');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Anita Dewi']);
    $service = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Facial Treatment']);

    $date = Carbon::parse('2026-10-10 10:00:00');

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'code' => 'BOOK-CSV-999',
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 250000,
        'start_at' => $date,
        'end_at' => $date->copy()->addHour(),
    ]);

    $response = $this->actingAs($owner)->get(route('owner.reports.export', [
        'preset' => 'custom',
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-10',
    ]));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Get stream output content
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->not->toBeEmpty();
    // Check UTF-8 BOM
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    // Check content contains expected headers and booking code
    expect($content)->toContain('LAPORAN BISNIS & ANALITIK AMAN BOOKING')
        ->toContain('BOOK-CSV-999')
        ->toContain('Anita Dewi')
        ->toContain('Facial Treatment')
        ->toContain('COMPLETED');
});

test('unauthorized user without report.export permission is blocked from CSV export', function () {
    $env = $this->createTenantEnvironment('Barber Shop');
    $tenant = $env['tenant'];

    // Create Front Desk user (does NOT have report.export permission per RolePermissionSeeder)
    $frontDeskUser = \App\Domain\Identity\Models\User::factory()->create([
        'email' => 'frontdesk@barbershop.test',
    ]);
    BusinessMember::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $frontDeskUser->id,
        'role' => 'Front Desk',
    ]);
    setPermissionsTeamId($tenant->id);
    $frontDeskRole = Role::where('name', 'Front Desk')->first();
    if ($frontDeskRole) {
        $frontDeskUser->assignRole($frontDeskRole);
    }

    $response = $this->actingAs($frontDeskUser)->get(route('owner.reports.export'));

    $response->assertStatus(403);
});

test('filters isolate bookings accurately by service_id and resource_id', function () {
    $env = $this->createTenantEnvironment('Dental Hub');
    $tenant = $env['tenant'];

    $resType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'STAFF',
        'name' => 'Staff',
        'icon' => 'user',
        'is_staff' => true,
        'is_active' => true,
    ]);

    $resA = Resource::factory()->create(['tenant_id' => $tenant->id, 'resource_type_id' => $resType->id, 'name' => 'Dokter A']);
    $resB = Resource::factory()->create(['tenant_id' => $tenant->id, 'resource_type_id' => $resType->id, 'name' => 'Dokter B']);

    $svcA = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Cabut Gigi']);
    $svcB = Service::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Scaling Gigi']);

    $cust = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $date = Carbon::parse('2026-10-14 10:00:00');

    // Booking 1: Service A, Resource A
    $b1 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $cust->id,
        'service_id' => $svcA->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 200000,
        'start_at' => $date,
        'end_at' => $date->copy()->addHour(),
    ]);
    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $b1->id,
        'resource_id' => $resA->id,
        'role' => 'PRIMARY',
        'start_at' => $date,
        'end_at' => $date->copy()->addHour(),
        'status' => AllocationStatus::ACTIVE,
    ]);

    // Booking 2: Service B, Resource B
    $b2 = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $cust->id,
        'service_id' => $svcB->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 150000,
        'start_at' => $date->copy()->addHours(2),
        'end_at' => $date->copy()->addHours(3),
    ]);
    BookingAllocation::create([
        'tenant_id' => $tenant->id,
        'booking_id' => $b2->id,
        'resource_id' => $resB->id,
        'role' => 'PRIMARY',
        'start_at' => $date->copy()->addHours(2),
        'end_at' => $date->copy()->addHours(3),
        'status' => AllocationStatus::ACTIVE,
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);

    // Filter by Service A only
    $summarySvcA = $reportService->getSummary($tenant, [
        'preset' => 'custom',
        'date_from' => '2026-10-14',
        'date_to' => '2026-10-14',
        'service_id' => $svcA->id,
    ]);
    expect($summarySvcA['total_bookings'])->toBe(1)
        ->and($summarySvcA['total_revenue'])->toBe(200000);

    // Filter by Resource B only
    $summaryResB = $reportService->getSummary($tenant, [
        'preset' => 'custom',
        'date_from' => '2026-10-14',
        'date_to' => '2026-10-14',
        'resource_id' => $resB->id,
    ]);
    expect($summaryResB['total_bookings'])->toBe(1)
        ->and($summaryResB['total_revenue'])->toBe(150000);
});

test('daily trends output valid chronological structure and figures', function () {
    $env = $this->createTenantEnvironment('Fitness Center');
    $tenant = $env['tenant'];

    $svc = Service::factory()->create(['tenant_id' => $tenant->id]);
    $cust = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $day1 = Carbon::parse('2026-10-01 10:00:00');
    $day2 = Carbon::parse('2026-10-02 14:00:00');

    // Day 1 booking
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $cust->id,
        'service_id' => $svc->id,
        'status_category' => BookingStatusCategory::COMPLETED,
        'payment_status' => 'PAID',
        'total_idr' => 100000,
        'start_at' => $day1,
        'end_at' => $day1->copy()->addHour(),
    ]);

    // Day 2 booking
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $cust->id,
        'service_id' => $svc->id,
        'status_category' => BookingStatusCategory::CANCELLED,
        'payment_status' => 'UNPAID',
        'total_idr' => 100000,
        'start_at' => $day2,
        'end_at' => $day2->copy()->addHour(),
    ]);

    /** @var ReportService $reportService */
    $reportService = app(ReportService::class);
    $trends = $reportService->getDailyTrends($tenant, [
        'preset' => 'custom',
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-03',
    ]);

    expect($trends)->toHaveCount(3);

    // Day 1
    expect($trends[0]['date'])->toBe('2026-10-01')
        ->and($trends[0]['total_bookings'])->toBe(1)
        ->and($trends[0]['completed_bookings'])->toBe(1)
        ->and($trends[0]['revenue'])->toBe(100000);

    // Day 2
    expect($trends[1]['date'])->toBe('2026-10-02')
        ->and($trends[1]['total_bookings'])->toBe(1)
        ->and($trends[1]['cancelled_bookings'])->toBe(1)
        ->and($trends[1]['revenue'])->toBe(0);

    // Day 3
    expect($trends[2]['date'])->toBe('2026-10-03')
        ->and($trends[2]['total_bookings'])->toBe(0)
        ->and($trends[2]['revenue'])->toBe(0);
});

