<?php

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Resource\Services\ResourceAvailabilityService;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create(['owner_user_id' => $this->user->id]);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);

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

    $this->service = new ResourceAvailabilityService;
});

test('resource is available when slot falls inside operating hours without breaks or blocks', function () {
    $resource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    // Monday schedule: 09:00 - 17:00, break 12:00 - 13:00
    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $resource->id,
        'day_of_week' => 1, // Monday
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'breaks' => [
            ['start' => '12:00', 'end' => '13:00', 'title' => 'Istirahat Siang'],
        ],
    ]);

    // Next Monday at 10:00 to 11:00 Asia/Jakarta
    $monday = Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta');
    $mondayEnd = Carbon::parse('2026-10-12 11:00:00', 'Asia/Jakarta');

    $isAvailable = $this->service->isResourceAvailable($resource, $monday, $mondayEnd, 'Asia/Jakarta');
    expect($isAvailable)->toBeTrue();
});

test('resource is unavailable when slot overlaps with break interval', function () {
    $resource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $resource->id,
        'day_of_week' => 1, // Monday
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'breaks' => [
            ['start' => '12:00', 'end' => '13:00', 'title' => 'Istirahat'],
        ],
    ]);

    // Slot 12:30 - 13:30 (overlaps break 12:00 - 13:00)
    $start = Carbon::parse('2026-10-12 12:30:00', 'Asia/Jakarta');
    $end = Carbon::parse('2026-10-12 13:30:00', 'Asia/Jakarta');

    $isAvailable = $this->service->isResourceAvailable($resource, $start, $end, 'Asia/Jakarta');
    expect($isAvailable)->toBeFalse();
});

test('resource is unavailable outside working hours or on day off', function () {
    $resource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $resource->id,
        'day_of_week' => 1, // Monday
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $resource->id,
        'day_of_week' => 0, // Sunday
        'is_available' => false,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    // Sunday (Day off)
    $sundayStart = Carbon::parse('2026-10-11 10:00:00', 'Asia/Jakarta');
    $sundayEnd = Carbon::parse('2026-10-11 11:00:00', 'Asia/Jakarta');
    expect($this->service->isResourceAvailable($resource, $sundayStart, $sundayEnd, 'Asia/Jakarta'))->toBeFalse();

    // Monday before 09:00
    $earlyStart = Carbon::parse('2026-10-12 08:00:00', 'Asia/Jakarta');
    $earlyEnd = Carbon::parse('2026-10-12 09:00:00', 'Asia/Jakarta');
    expect($this->service->isResourceAvailable($resource, $earlyStart, $earlyEnd, 'Asia/Jakarta'))->toBeFalse();

    // Monday after 17:00
    $lateStart = Carbon::parse('2026-10-12 17:00:00', 'Asia/Jakarta');
    $lateEnd = Carbon::parse('2026-10-12 18:00:00', 'Asia/Jakarta');
    expect($this->service->isResourceAvailable($resource, $lateStart, $lateEnd, 'Asia/Jakarta'))->toBeFalse();
});

test('resource is unavailable when in non-available state or archived', function () {
    $states = ['BLOCKED', 'MAINTENANCE', 'INACTIVE'];

    foreach ($states as $state) {
        $resource = Resource::factory()->create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'resource_type_id' => $this->staffType->id,
            'state' => $state,
        ]);

        $start = Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta');
        $end = Carbon::parse('2026-10-12 11:00:00', 'Asia/Jakarta');

        expect($this->service->isResourceAvailable($resource, $start, $end, 'Asia/Jakarta'))->toBeFalse();
    }

    // Archived resource
    $archivedResource = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
        'archived_at' => now(),
    ]);

    expect($this->service->isResourceAvailable($archivedResource, Carbon::parse('2026-10-12 10:00:00'), Carbon::parse('2026-10-12 11:00:00')))->toBeFalse();
});

test('time blocks and leaves prevent availability for specific resource or all resources', function () {
    $therapist = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->staffType->id,
        'state' => 'AVAILABLE',
    ]);

    ResourceSchedule::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist->id,
        'day_of_week' => 1,
        'is_available' => true,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    // Create time block (Cuti) for therapist from 13:00 to 16:00
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => $therapist->id,
        'start_at' => Carbon::parse('2026-10-12 13:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'end_at' => Carbon::parse('2026-10-12 16:00:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'reason' => 'Cuti Dokter',
        'is_all_resources' => false,
    ]);

    // Slot 14:00 - 15:00 falls inside cuti -> false
    $blockedStart = Carbon::parse('2026-10-12 14:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    $blockedEnd = Carbon::parse('2026-10-12 15:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    expect($this->service->isResourceAvailable($therapist, $blockedStart, $blockedEnd, 'Asia/Jakarta'))->toBeFalse();

    // Slot 10:00 - 11:00 outside cuti -> true
    $freeStart = Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    $freeEnd = Carbon::parse('2026-10-12 11:00:00', 'Asia/Jakarta')->setTimezone('UTC');
    expect($this->service->isResourceAvailable($therapist, $freeStart, $freeEnd, 'Asia/Jakarta'))->toBeTrue();

    // Now create all-resources time block (e.g. Renovasi Klinik)
    TimeBlock::create([
        'tenant_id' => $this->tenant->id,
        'resource_id' => null,
        'start_at' => Carbon::parse('2026-10-12 09:30:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'end_at' => Carbon::parse('2026-10-12 11:30:00', 'Asia/Jakarta')->setTimezone('UTC'),
        'reason' => 'Pemadaman Listrik & Renovasi',
        'is_all_resources' => true,
    ]);

    // Previously free slot 10:00 - 11:00 is now blocked
    expect($this->service->isResourceAvailable($therapist, $freeStart, $freeEnd, 'Asia/Jakarta'))->toBeFalse();
});

test('skill compatibility rules reject unqualified staff per PRD 160', function () {
    // Therapist A: Massage, Reflexology
    $therapistA = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Therapist A',
        'skills' => ['Massage', 'Reflexology'],
    ]);

    // Therapist B: Massage, Hot Stone
    $therapistB = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Therapist B',
        'skills' => ['Massage', 'Hot Stone'],
    ]);

    // Therapist C: Facial
    $therapistC = Resource::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Therapist C',
        'skills' => ['Facial'],
    ]);

    // Service requiring Hot Stone
    $hotStoneSkill = ['Hot Stone'];

    expect($this->service->checkSkillCompatibility($therapistB, $hotStoneSkill))->toBeTrue();
    expect($this->service->checkSkillCompatibility($therapistA, $hotStoneSkill))->toBeFalse();
    expect($this->service->checkSkillCompatibility($therapistC, $hotStoneSkill))->toBeFalse();

    // Service requiring Facial
    $facialSkill = ['Facial'];
    expect($this->service->checkSkillCompatibility($therapistC, $facialSkill))->toBeTrue();
    expect($this->service->checkSkillCompatibility($therapistA, $facialSkill))->toBeFalse();
});
