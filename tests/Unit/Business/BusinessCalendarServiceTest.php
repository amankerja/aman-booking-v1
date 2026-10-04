<?php

namespace Tests\Unit\Business;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Business\Models\CalendarException;
use App\Domain\Business\Services\BusinessCalendarService;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    TenantContext::clear();
    $this->calendarService = new BusinessCalendarService;
});

afterEach(function () {
    TenantContext::clear();
});

test('service converts utc to local timezone and vice versa accurately', function () {
    // 2026-10-05 03:00:00 UTC = 10:00:00 WIB (Asia/Jakarta, +7)
    $utc = Carbon::create(2026, 10, 5, 3, 0, 0, 'UTC');

    $wib = $this->calendarService->toLocal($utc, 'Asia/Jakarta');
    expect($wib->timezoneName)->toBe('Asia/Jakarta')
        ->and($wib->hour)->toBe(10)
        ->and($wib->minute)->toBe(0);

    // 2026-10-05 03:00:00 UTC = 11:00:00 WITA (Asia/Makassar, +8)
    $wita = $this->calendarService->toLocal($utc, 'Asia/Makassar');
    expect($wita->timezoneName)->toBe('Asia/Makassar')
        ->and($wita->hour)->toBe(11);

    // Reverse: 10:00 WIB back to UTC should be 03:00 UTC
    $backToUtc = $this->calendarService->toUtc($wib, 'Asia/Jakarta');
    expect($backToUtc->timezoneName)->toBe('UTC')
        ->and($backToUtc->hour)->toBe(3);
});

test('service initializes default 7 operating hours for business', function () {
    $tenant = Tenant::factory()->create();
    TenantContext::setTenant($tenant);

    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);

    $this->calendarService->initializeDefaultHours($business);

    $hours = BusinessHour::where('business_id', $business->id)->orderBy('day_of_week')->get();
    expect($hours)->toHaveCount(7);

    // Sunday (0) closed
    expect($hours[0]->day_of_week)->toBe(0)
        ->and($hours[0]->is_open)->toBeFalse();

    // Monday (1) open with lunch break
    expect($hours[1]->day_of_week)->toBe(1)
        ->and($hours[1]->is_open)->toBeTrue()
        ->and($hours[1]->open_time)->toBe('09:00:00')
        ->and($hours[1]->close_time)->toBe('17:00:00')
        ->and($hours[1]->breaks)->toHaveCount(1)
        ->and($hours[1]->breaks[0]['name'])->toBe('Istirahat Siang');
});

test('isBusinessOpenAt correctly checks standard operating hours', function () {
    $tenant = Tenant::factory()->create();
    TenantContext::setTenant($tenant);

    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);
    $this->calendarService->initializeDefaultHours($business);

    // 2026-10-05 is a Monday.
    // 03:00:00 UTC is 10:00:00 WIB (Mon 09:00 - 17:00, lunch 12:00 - 13:00) -> Open!
    $mondayMorningUtc = Carbon::create(2026, 10, 5, 3, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $mondayMorningUtc))->toBeTrue();

    // 01:00:00 UTC is 08:00:00 WIB (before 09:00 open) -> Closed!
    $mondayEarlyUtc = Carbon::create(2026, 10, 5, 1, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $mondayEarlyUtc))->toBeFalse();

    // 11:00:00 UTC is 18:00:00 WIB (after 17:00 close) -> Closed!
    $mondayEveningUtc = Carbon::create(2026, 10, 5, 11, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $mondayEveningUtc))->toBeFalse();

    // 2026-10-04 is a Sunday -> Closed!
    $sundayUtc = Carbon::create(2026, 10, 4, 3, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $sundayUtc))->toBeFalse();
});

test('isBusinessOpenAt returns false during break intervals', function () {
    $tenant = Tenant::factory()->create();
    TenantContext::setTenant($tenant);

    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);
    $this->calendarService->initializeDefaultHours($business);

    // Monday 2026-10-05 at 12:30:00 WIB is 05:30:00 UTC (Lunch break is 12:00 - 13:00)
    $lunchBreakUtc = Carbon::create(2026, 10, 5, 5, 30, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $lunchBreakUtc))->toBeFalse();

    // Monday 2026-10-05 at 13:15:00 WIB is 06:15:00 UTC (Break ended at 13:00) -> Open!
    $afterLunchUtc = Carbon::create(2026, 10, 5, 6, 15, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $afterLunchUtc))->toBeTrue();
});

test('isBusinessOpenAt respects calendar exceptions over regular schedule', function () {
    $tenant = Tenant::factory()->create();
    TenantContext::setTenant($tenant);

    $business = Business::factory()->create([
        'tenant_id' => $tenant->id,
        'timezone' => 'Asia/Jakarta',
    ]);
    $this->calendarService->initializeDefaultHours($business);

    // Add a Holiday exception on Monday 2026-10-05
    CalendarException::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'type' => 'HOLIDAY',
        'date' => '2026-10-05',
        'title' => 'Hari Libur Nasional',
        'is_closed' => true,
    ]);

    // Monday 10:00 WIB normally open, but today is holiday -> Closed!
    $holidayUtc = Carbon::create(2026, 10, 5, 3, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $holidayUtc))->toBeFalse();

    // Add a Special Open exception on Sunday 2026-10-11 (normally closed on Sunday)
    CalendarException::create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'type' => 'SPECIAL_OPEN',
        'date' => '2026-10-11',
        'title' => 'Bazar Hari Minggu',
        'is_closed' => false,
        'open_time' => '10:00:00',
        'close_time' => '16:00:00',
    ]);

    // Sunday 2026-10-11 at 11:00 WIB (04:00 UTC) -> Special Open -> Open!
    $sundaySpecialUtc = Carbon::create(2026, 10, 11, 4, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $sundaySpecialUtc))->toBeTrue();

    // Sunday 2026-10-11 at 17:00 WIB (10:00 UTC) -> Past special close (16:00) -> Closed!
    $sundayAfterSpecialUtc = Carbon::create(2026, 10, 11, 10, 0, 0, 'UTC');
    expect($this->calendarService->isBusinessOpenAt($business, $sundayAfterSpecialUtc))->toBeFalse();
});
