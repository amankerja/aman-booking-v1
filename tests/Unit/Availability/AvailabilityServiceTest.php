<?php

namespace Tests\Unit\Availability;

use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Models\CalendarException;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\ServiceResourceRule;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    TenantContext::clear();

    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_user_id' => $this->user->id]);
    TenantContext::setTenant($this->tenant);

    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);

    // Setup standard business hours for Monday (Day 1): 09:00 - 18:00
    BusinessHour::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'day_of_week' => 1, // Monday
        'is_open' => true,
        'open_time' => '09:00:00',
        'close_time' => '18:00:00',
        'breaks' => [],
    ]);

    // Setup Sunday (Day 0) as Closed
    BusinessHour::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'day_of_week' => 0, // Sunday
        'is_open' => false,
        'open_time' => '09:00:00',
        'close_time' => '17:00:00',
        'breaks' => [],
    ]);

    // Standard Staff Resource Type
    $this->staffType = ResourceType::firstOrCreate(
        ['code' => 'STAFF'],
        [
            'name' => 'Staff / Terapis / Praktisi',
            'icon' => 'User',
            'is_staff' => true,
            'is_space' => false,
            'is_equipment' => false,
            'is_active' => true,
        ]
    );

    $this->service = new AvailabilityService;
});

afterEach(function () {
    TenantContext::clear();
});

test('basic slot generation within business and staff operating hours with step', function () {
    // 60-minute service
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Basic Haircut',
        'slug' => 'basic-haircut',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Barber John',
        'state' => 'AVAILABLE',
    ]);

    // Staff Monday schedule: 09:00 - 18:00
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'breaks' => [],
    ]);

    // Date: Monday 2026-10-12
    $date = '2026-10-12';
    $slots = $this->service->getSlotsForDate($this->tenant, $service, $date, [
        'slot_step_minutes' => 60,
    ]);

    expect($slots)->not->toBeEmpty();

    // 09:00-10:00 to 17:00-18:00 should be available (9 slots total)
    $availableSlots = $slots->where('is_available', true);
    expect($availableSlots->count())->toBe(9);

    $firstSlot = $availableSlots->first();
    expect($firstSlot['start_time'])->toBe('09:00')
        ->and($firstSlot['end_time'])->toBe('10:00')
        ->and($firstSlot['is_available'])->toBeTrue()
        ->and($firstSlot['available_staff_ids'])->toContain($staff->id);

    $lastSlot = $availableSlots->last();
    expect($lastSlot['start_time'])->toBe('17:00')
        ->and($lastSlot['end_time'])->toBe('18:00')
        ->and($lastSlot['is_available'])->toBeTrue();
});

test('PRD 197: duration + staff example rejects slot exceeding staff shift with RESOURCE_UNAVAILABLE_FOR_FULL_DURATION', function () {
    // Business is open until 21:00 so therapist shift (18:00) is the constraint
    BusinessHour::where('business_id', $this->business->id)
        ->where('day_of_week', 1)
        ->update(['open_time' => '09:00:00', 'close_time' => '21:00:00']);

    // Service: Massage Premium, Duration: 120 min
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Massage Premium',
        'slug' => 'massage-premium',
        'price_idr' => 200000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 120,
    ]);

    // Therapist A: Available 10:00–18:00
    $therapist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist A',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
        'breaks' => [],
    ]);

    // Customer picks 17:00 (ends 19:00, which exceeds 18:00 shift end)
    $date = '2026-10-12';
    $slots = $this->service->getSlotsForDate($this->tenant, $service, $date, [
        'slot_step_minutes' => 60,
    ]);

    $slot16 = $slots->firstWhere('start_time', '16:00');
    expect($slot16)->not->toBeNull()
        ->and($slot16['is_available'])->toBeTrue()
        ->and($slot16['end_time'])->toBe('18:00');

    $slot17 = $slots->firstWhere('start_time', '17:00');
    expect($slot17)->not->toBeNull()
        ->and($slot17['is_available'])->toBeFalse()
        ->and($slot17['end_time'])->toBe('19:00')
        ->and($slot17['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION)
        ->and($slot17['reason_message'])->toContain('Terapis');
});

test('PRD 198: duration + resource example rejects slot exceeding room or resource availability', function () {
    // Business is open until 21:00 so resource schedule (18:00) is the constraint
    BusinessHour::where('business_id', $this->business->id)
        ->where('day_of_week', 1)
        ->update(['open_time' => '09:00:00', 'close_time' => '21:00:00']);

    // Service: 90 min
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Spa Treatment',
        'slug' => 'spa-treatment',
        'price_idr' => 150000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 90,
    ]);

    // Staff available 10:00–18:00
    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Staff Room Specialist',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
        'breaks' => [],
    ]);

    // Slot 17:00 ends at 18:30 -> outside shift
    $date = '2026-10-12';
    $slots = $this->service->getSlotsForDate($this->tenant, $service, $date, [
        'slot_step_minutes' => 30,
    ]);

    $slot17 = $slots->firstWhere('start_time', '17:00');
    expect($slot17)->not->toBeNull()
        ->and($slot17['is_available'])->toBeFalse()
        ->and($slot17['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION);
});

test('PRD 19: existing booking conflict blocks overlapping slots with SLOT_TAKEN', function () {
    // Business is open 08:00 - 18:00
    BusinessHour::where('business_id', $this->business->id)
        ->where('day_of_week', 1)
        ->update(['open_time' => '08:00:00', 'close_time' => '18:00:00']);

    // Service: 60 minutes
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Massage 60m',
        'slug' => 'massage-60m',
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Therapist A: 08:00–18:00
    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Therapist A',
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
        'breaks' => [],
    ]);

    // Fixture booking allocation: 09:00 - 10:00 booked for Therapist A on 2026-10-12
    $existingAllocations = [
        [
            'resource_id' => $staff->id,
            'start_at' => '2026-10-12 09:00:00',
            'end_at' => '2026-10-12 10:00:00',
        ],
    ];

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 30,
        'existing_allocations' => $existingAllocations,
    ]);

    // 08:00 - 09:00: available
    $slot0800 = $slots->firstWhere('start_time', '08:00');
    expect($slot0800['is_available'])->toBeTrue();

    // 09:00 - 10:00 is booked -> NOT available
    $slot0900 = $slots->firstWhere('start_time', '09:00');
    expect($slot0900['is_available'])->toBeFalse()
        ->and($slot0900['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN)
        ->and($slot0900['reason_message'])->toBe('Slot tersebut baru saja dipesan customer lain. Silakan pilih waktu lain.');

    // 09:30 - 10:30 overlaps with 09:00-10:00 -> NOT available
    $slot0930 = $slots->firstWhere('start_time', '09:30');
    expect($slot0930['is_available'])->toBeFalse()
        ->and($slot0930['reason_code'])->toBe(AvailabilityService::REASON_SLOT_TAKEN);

    // 10:00 - 11:00 does NOT overlap -> available
    $slot1000 = $slots->firstWhere('start_time', '10:00');
    expect($slot1000['is_available'])->toBeTrue();
});

test('business holiday or blackout date returns OUTSIDE_BUSINESS_HOURS for all slots', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut',
        'slug' => 'haircut',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Create Holiday Exception on 2026-10-12
    CalendarException::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'type' => 'HOLIDAY',
        'title' => 'Hari Libur Nasional',
        'date' => '2026-10-12',
        'is_closed' => true,
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12');
    $availableSlots = $slots->where('is_available', true);

    expect($availableSlots)->toBeEmpty();
    expect($slots->first()['reason_code'])->toBe(AvailabilityService::REASON_OUTSIDE_BUSINESS_HOURS);
});

test('closed day of week returns OUTSIDE_BUSINESS_HOURS for all slots', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut',
        'slug' => 'haircut',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Sunday (Day 0) is closed
    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-18'); // Sunday
    $availableSlots = $slots->where('is_available', true);

    expect($availableSlots)->toBeEmpty();
});

test('business break interval prevents slots overlapping with the break', function () {
    // Update Monday business hours to include break 12:00 - 13:00
    BusinessHour::where('business_id', $this->business->id)
        ->where('day_of_week', 1)
        ->update([
            'breaks' => [
                ['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
            ],
        ]);

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 30,
    ]);

    // 11:30 - 12:30 overlaps with business break 12:00-13:00 -> NOT available
    $slot1130 = $slots->firstWhere('start_time', '11:30');
    expect($slot1130['is_available'])->toBeFalse()
        ->and($slot1130['reason_code'])->toBe(AvailabilityService::REASON_OUTSIDE_BUSINESS_HOURS);

    // 12:00 - 13:00 overlaps -> NOT available
    $slot1200 = $slots->firstWhere('start_time', '12:00');
    expect($slot1200['is_available'])->toBeFalse()
        ->and($slot1200['reason_code'])->toBe(AvailabilityService::REASON_OUTSIDE_BUSINESS_HOURS);

    // 13:00 - 14:00 is after break -> available
    $slot1300 = $slots->firstWhere('start_time', '13:00');
    expect($slot1300['is_available'])->toBeTrue();
});

test('staff break interval prevents slots overlapping with the staff break', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    // Staff break: 13:00 - 14:00
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'breaks' => [
            ['title' => 'Staff Break', 'start' => '13:00', 'end' => '14:00'],
        ],
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 30,
    ]);

    $slot1230 = $slots->firstWhere('start_time', '12:30');
    expect($slot1230['is_available'])->toBeFalse()
        ->and($slot1230['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION);

    $slot1400 = $slots->firstWhere('start_time', '14:00');
    expect($slot1400['is_available'])->toBeTrue();
});

test('staff time block / cuti excludes staff from availability', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Staff on leave from 14:00 to 16:00
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'reason' => 'Cuti Dokter',
        'start_at' => Carbon::parse('2026-10-12 14:00:00', 'Asia/Jakarta'),
        'end_at' => Carbon::parse('2026-10-12 16:00:00', 'Asia/Jakarta'),
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);

    $slot1400 = $slots->firstWhere('start_time', '14:00');
    expect($slot1400['is_available'])->toBeFalse()
        ->and($slot1400['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION);

    $slot1600 = $slots->firstWhere('start_time', '16:00');
    expect($slot1600['is_available'])->toBeTrue();
});

test('PRD 160: skill compatibility ensures only qualified staff provide availability', function () {
    // Service requires "Hot Stone" skill
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Hot Stone Massage',
        'slug' => 'hot-stone-massage',
        'price_idr' => 250000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    // Service Resource Rule requiring "Hot Stone"
    ServiceResourceRule::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $service->id,
        'resource_type_id' => $this->staffType->id,
        'required_skills' => ['Hot Stone'],
        'is_required' => true,
        'assignment_mode' => 'AUTO_ASSIGN',
        'quantity' => 1,
    ]);

    // Staff A: Only Swedish Massage
    $staffA = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Staff Regular',
        'skills' => ['Swedish Massage'],
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staffA->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Staff B: Has Hot Stone skill
    $staffB = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'name' => 'Staff Certified',
        'skills' => ['Swedish Massage', 'Hot Stone'],
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staffB->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // When customer chooses "Any Staff" -> Available via Staff B only
    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);

    $slot0900 = $slots->firstWhere('start_time', '09:00');
    expect($slot0900['is_available'])->toBeTrue()
        ->and($slot0900['available_staff_ids'])->toEqual([$staffB->id])
        ->and($slot0900['available_staff_ids'])->not->toContain($staffA->id);

    // If customer explicitly requests Staff A who lacks the skill -> Slot should be unavailable
    $slotsPreferredStaffA = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'preferred_staff_id' => $staffA->id,
        'slot_step_minutes' => 60,
    ]);

    $slot0900StaffA = $slotsPreferredStaffA->firstWhere('start_time', '09:00');
    expect($slot0900StaffA['is_available'])->toBeFalse()
        ->and($slot0900StaffA['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION);
});

test('variant and add-ons modify duration and recalculate slot feasibility', function () {
    // Open until 20:00 so staff shift (18:00) is the constraint
    BusinessHour::where('business_id', $this->business->id)
        ->where('day_of_week', 1)
        ->update(['open_time' => '09:00:00', 'close_time' => '20:00:00']);

    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut Special',
        'slug' => 'haircut-special',
        'price_idr' => 60000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $addon = ServiceAddon::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'service_id' => $service->id,
        'name' => 'Creambath Extra',
        'duration_minutes' => 60, // 60 min base + 60 min addon = 120 min total
        'price_idr' => 40000,
        'is_active' => true,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
    ]);

    // Service duration without addon is 60m -> 17:00 is available (17:00 to 18:00)
    $slotsWithoutAddon = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);
    expect($slotsWithoutAddon->firstWhere('start_time', '17:00')['is_available'])->toBeTrue();

    // With addon (120m total) -> 17:00 would end at 19:00 -> NOT available
    $slotsWithAddon = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'addon_ids' => [$addon->id],
        'slot_step_minutes' => 60,
    ]);

    $slot1700 = $slotsWithAddon->firstWhere('start_time', '17:00');
    expect($slot1700['is_available'])->toBeFalse()
        ->and($slot1700['duration_minutes'])->toBe(120)
        ->and($slot1700['reason_code'])->toBe(AvailabilityService::REASON_RESOURCE_UNAVAILABLE_FOR_FULL_DURATION);
});

test('getSlotsForDateRange returns slots for each date within range', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Quick Consultation',
        'slug' => 'quick-consultation',
        'price_idr' => 100000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 30,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1, // Monday
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '12:00:00',
    ]);

    $rangeResults = $this->service->getSlotsForDateRange(
        $this->tenant,
        $service,
        '2026-10-12',
        '2026-10-13',
        ['slot_step_minutes' => 60]
    );

    expect($rangeResults)->toBeArray()
        ->and($rangeResults)->toHaveKeys(['2026-10-12', '2026-10-13']);
});

test('isSlotAvailable accurately checks a single specific timestamp', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // Check valid slot at 10:00
    $check10 = $this->service->isSlotAvailable($this->tenant, $service, '2026-10-12 10:00:00');
    expect($check10['is_available'])->toBeTrue()
        ->and($check10['reason_code'])->toBeNull()
        ->and($check10['available_staff_ids'])->toContain($staff->id);

    // Check invalid slot outside operating hours at 22:00
    $check22 = $this->service->isSlotAvailable($this->tenant, $service, '2026-10-12 22:00:00');
    expect($check22['is_available'])->toBeFalse()
        ->and($check22['reason_code'])->toBe(AvailabilityService::REASON_OUTSIDE_BUSINESS_HOURS);
});

test('getAvailableSlotsForDate filters out all unavailable slots', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '10:00:00',
        'end_time' => '13:00:00',
    ]);

    $availableSlots = $this->service->getAvailableSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);

    expect($availableSlots->every(fn ($slot) => $slot['is_available'] === true))->toBeTrue()
        ->and($availableSlots->count())->toBe(3); // 10:00, 11:00, 12:00
});

test('global time block (is_all_resources) blocks all staff availability', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff1 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff1->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    $staff2 = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff2->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ]);

    // All-resources block (e.g. All-Hands Meeting or Renovation) 10:00 to 12:00
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => null,
        'is_all_resources' => true,
        'reason' => 'All-Hands Meeting',
        'start_at' => Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'),
        'end_at' => Carbon::parse('2026-10-12 12:00:00', 'Asia/Jakarta'),
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);

    expect($slots->firstWhere('start_time', '10:00')['is_available'])->toBeFalse()
        ->and($slots->firstWhere('start_time', '11:00')['is_available'])->toBeFalse()
        ->and($slots->firstWhere('start_time', '13:00')['is_available'])->toBeTrue();
});

test('special open hours in calendar exception override standard business hours', function () {
    $service = Service::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut 60m',
        'slug' => 'haircut-60m',
        'price_idr' => 50000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 60,
    ]);

    $staff = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $staff->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '08:00:00',
        'end_time' => '20:00:00',
    ]);

    // Monday normally closes at 18:00. Special close at 12:00 on this Monday.
    CalendarException::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'type' => 'SPECIAL_CLOSE',
        'title' => 'Tutup Lebih Awal',
        'date' => '2026-10-12',
        'is_closed' => false,
        'open_time' => '09:00:00',
        'close_time' => '12:00:00',
    ]);

    $slots = $this->service->getSlotsForDate($this->tenant, $service, '2026-10-12', [
        'slot_step_minutes' => 60,
    ]);

    expect($slots->firstWhere('start_time', '09:00')['is_available'])->toBeTrue()
        ->and($slots->firstWhere('start_time', '10:00')['is_available'])->toBeTrue()
        ->and($slots->firstWhere('start_time', '11:00')['is_available'])->toBeTrue() // 11:00-12:00
        ->and($slots->firstWhere('start_time', '12:00'))->toBeNull(); // Day closes at 12:00, no slots after
});
