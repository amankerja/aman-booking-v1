<?php

use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceAddon;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Service\Models\ServiceVariant;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Klinik Sentosa Estetika',
        'slug' => 'klinik-sentosa',
        'timezone' => 'Asia/Jakarta',
        'whatsapp' => '081234567890',
        'published_at' => Carbon::now()->subDays(5),
    ]);

    // Setup 7 operating days (09:00 - 18:00)
    for ($day = 0; $day <= 6; $day++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'breaks' => [],
        ]);
    }

    $this->resourceType = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'STAFF',
        'name' => 'Dokter & Terapis',
        'is_staff' => true,
    ]);

    $this->staff = Resource::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $this->resourceType->id,
        'name' => 'Dr. Jessica Tan',
        'code' => 'ST-001',
        'capacity' => 1,
        'visibility' => 'PUBLIC',
        'state' => 'AVAILABLE',
    ]);

    $this->category = ServiceCategory::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Perawatan Wajah',
        'slug' => 'perawatan-wajah',
    ]);

    $this->service = Service::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'category_id' => $this->category->id,
        'name' => 'Facial Deep Cleanse Glow',
        'duration_minutes' => 60,
        'price_idr' => 250000,
        'capacity' => 1,
        'buffer_before' => 0,
        'buffer_after' => 0,
        'is_active' => true,
        'description' => 'Pembersihan pori mendalam dengan serum vitamin C dan masker hidrogel.',
    ]);

    $this->variant = ServiceVariant::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->service->id,
        'name' => 'Premium Gold Serum',
        'duration_minutes' => 75,
        'price_idr' => 350000,
        'order' => 1,
    ]);

    $this->addon = ServiceAddon::create([
        'tenant_id' => $this->tenant->id,
        'service_id' => $this->service->id,
        'name' => 'Eye Treatment Collagen',
        'duration_minutes' => 15,
        'price_idr' => 50000,
        'order' => 1,
    ]);
});

test('public booking page renders with business data, services and selectable staff', function () {
    $response = $this->get('/klinik-sentosa/booking');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Booking')
        ->where('business.name', 'Klinik Sentosa Estetika')
        ->where('business.slug', 'klinik-sentosa')
        ->where('business.timezone', 'Asia/Jakarta')
        ->has('services', 1)
        ->has('staff', 1)
        ->where('staff.0.name', 'Dr. Jessica Tan')
    );
});

test('public booking page preselects service and quickMode from query parameters', function () {
    $response = $this->get('/klinik-sentosa/booking?service_id='.$this->service->id.'&quick=1');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Booking')
        ->where('preselectedServiceId', $this->service->id)
        ->where('quickMode', true)
    );
});

test('availability endpoint returns real-time slot schedule with timezone label', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(2)->format('Y-m-d');

    $response = $this->getJson("/klinik-sentosa/availability?service_id={$this->service->id}&date={$testDate}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'date',
        'timezone',
        'slots' => [
            '*' => [
                'start_time',
                'end_time',
                'start_at',
                'end_at',
                'available',
                'reason',
                'message',
            ],
        ],
    ]);

    $response->assertJson([
        'date' => $testDate,
        'timezone' => 'Asia/Jakarta',
    ]);

    $slots = $response->json('slots');
    expect($slots)->toBeArray();
    expect(count($slots))->toBeGreaterThan(0);
    expect($slots[0]['available'])->toBeTrue();
});

test('availability endpoint returns 404 for unknown service or unpublished business', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(1)->format('Y-m-d');

    // 1. Unknown service -> 404
    $resService = $this->getJson("/klinik-sentosa/availability?service_id=999999&date={$testDate}");
    $resService->assertStatus(404);

    // 2. Unpublished business -> 404
    $this->business->update(['published_at' => null]);
    $resBusiness = $this->getJson("/klinik-sentosa/availability?service_id={$this->service->id}&date={$testDate}");
    $resBusiness->assertStatus(404);
});

test('customer can create booking reservation successfully', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(2)->setTime(10, 0, 0);
    $idempotencyKey = (string) Str::uuid();

    $response = $this->postJson('/klinik-sentosa/booking', [
        'service_id' => $this->service->id,
        'variant_id' => $this->variant->id,
        'addon_ids' => [$this->addon->id],
        'staff_id' => $this->staff->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Siti Rahmawati',
        'customer_phone' => '081299887766',
        'customer_email' => 'siti@example.com',
        'customer_notes' => 'Kulit sensitif',
        'idempotency_key' => $idempotencyKey,
    ], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'code',
        'redirect_url',
    ]);

    $code = $response->json('code');
    expect($code)->toStartWith('BK-');

    // Verify database record
    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'code' => $code,
        'service_id' => $this->service->id,
        'idempotency_key' => $idempotencyKey,
    ]);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Siti Rahmawati',
        'phone_e164' => '+6281299887766',
    ]);
});

test('idempotency key prevents duplicate bookings upon multiple submissions', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(3)->setTime(14, 0, 0);
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'service_id' => $this->service->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Ahmad Fauzi',
        'customer_phone' => '081311223344',
        'idempotency_key' => $idempotencyKey,
    ];

    // Submit 1
    $res1 = $this->postJson('/klinik-sentosa/booking', $payload, [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    $res1->assertStatus(200);
    $code1 = $res1->json('code');

    // Submit 2 (same key)
    $res2 = $this->postJson('/klinik-sentosa/booking', $payload, [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    $res2->assertStatus(200);
    $code2 = $res2->json('code');

    // Must return the exact same booking code
    expect($code2)->toBe($code1);

    // Exactly 1 booking record in database
    expect(Booking::where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

test('booking rejected with friendly message when slot is already booked by another customer', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(2)->setTime(11, 0, 0);

    // 1. Customer A books the slot
    $this->postJson('/klinik-sentosa/booking', [
        'service_id' => $this->service->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Customer A',
        'customer_phone' => '081111111111',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    // 2. Customer B attempts to book the exact same slot
    $resB = $this->postJson('/klinik-sentosa/booking', [
        'service_id' => $this->service->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Customer B',
        'customer_phone' => '082222222222',
    ], ['Idempotency-Key' => (string) Str::uuid()]);

    // Must fail with 422, error_code SLOT_TAKEN and friendly Indonesian message
    $resB->assertStatus(422);
    $resB->assertJson([
        'error_code' => 'SLOT_TAKEN',
    ]);
    expect($resB->json('message'))->toContain('baru saja dipesan customer lain');
});

test('booking success page renders with booking details and manage link', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(2)->setTime(15, 0, 0);

    $createRes = $this->postJson('/klinik-sentosa/booking', [
        'service_id' => $this->service->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Bambang S',
        'customer_phone' => '081555555555',
    ]);
    $code = $createRes->json('code');

    $response = $this->get("/klinik-sentosa/booking/success/{$code}");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/BookingSuccess')
        ->where('booking.code', $code)
        ->where('booking.status', 'CONFIRMED')
        ->where('business.slug', 'klinik-sentosa')
        ->has('booking.manage_token')
    );
});

test('booking manage page renders with booking details', function () {
    $testDate = Carbon::now('Asia/Jakarta')->addDays(2)->setTime(16, 0, 0);

    $this->postJson('/klinik-sentosa/booking', [
        'service_id' => $this->service->id,
        'start_at' => $testDate->toIso8601String(),
        'customer_name' => 'Dewi Lestari',
        'customer_phone' => '081666666666',
    ]);

    /** @var Booking $booking */
    $booking = Booking::latest('id')->first();

    $response = $this->get("/klinik-sentosa/booking/manage/{$booking->manage_token}");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/BookingManage')
        ->where('booking.code', $booking->code)
        ->where('booking.manage_token', $booking->manage_token)
    );
});
