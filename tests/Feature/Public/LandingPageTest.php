<?php

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'ACTIVE']);
    $this->business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Barber Keren Sentosa',
        'slug' => 'barber-keren',
        'description' => 'Barbershop modern dengan fasilitas full AC dan terapis potong rambut bersertifikat.',
        'address' => 'Jl. Senopati No. 45',
        'city' => 'Jakarta Selatan',
        'province' => 'DKI Jakarta',
        'postal_code' => '12190',
        'phone' => '0217201234',
        'whatsapp' => '081298765432',
        'timezone' => 'Asia/Jakarta',
        'published_at' => Carbon::now()->subDays(5),
    ]);

    // Setup 7 operating days
    for ($day = 0; $day <= 6; $day++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '21:00:00',
            'breaks' => [],
        ]);
    }

    $this->category = ServiceCategory::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut & Styling',
        'slug' => 'haircut-styling',
    ]);

    $this->service = Service::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'category_id' => $this->category->id,
        'name' => 'Gentleman Haircut & Wash',
        'duration_minutes' => 45,
        'price_idr' => 120000,
        'capacity' => 1,
        'is_active' => true,
        'description' => 'Potong rambut presisi, cuci rambut segar, dan pijat relaksasi kepala.',
    ]);
});

test('public landing page renders server-side Blade with high conversion elements and SEO metadata', function () {
    $response = $this->get('/barber-keren');

    $response->assertStatus(200);

    // Business details
    $response->assertSee('Barber Keren Sentosa');
    $response->assertSee('Gentleman Haircut & Wash');
    $response->assertSee('Rp 120.000');
    $response->assertSee('45 Menit');
    $response->assertSee('Booking Sekarang');
    $response->assertSee('WhatsApp');

    // SEO Title & Description
    $response->assertSee('<title>Barber Keren Sentosa — Reservasi & Jadwal Booking Online</title>', false);
    $response->assertSee('name="description"', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('schema.org', false);
    $response->assertSee('LocalBusiness', false);

    // HTML size budget: must be well under 100 KB per PRD 204.8 and prompt 2.1
    $htmlLength = strlen($response->getContent());
    expect($htmlLength)->toBeLessThan(100 * 1024); // < 100 KB
});

test('landing page returns 404 if business is not published', function () {
    $this->business->update(['published_at' => null]);

    $response = $this->get('/barber-keren');

    $response->assertStatus(404);
});

test('landing page returns 404 for non-existent business slug', function () {
    $response = $this->get('/slug-yang-tidak-ada-12345');

    $response->assertStatus(404);
});

test('landing page returns 503 friendly unavailable page when tenant is suspended', function () {
    $this->tenant->update(['status' => 'SUSPENDED']);

    $response = $this->get('/barber-keren');

    $response->assertStatus(503);
    $response->assertSee('Layanan Sementara Tidak Tersedia');
    $response->assertSee('Barber Keren Sentosa');
});

test('public booking route renders Inertia booking component with business and services', function () {
    $response = $this->get('/barber-keren/booking');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Booking')
        ->has('business.name')
        ->has('services', 1)
        ->where('business.slug', 'barber-keren')
    );
});

test('public booking route returns 404 when unpublished and 503 when suspended', function () {
    // 1. Unpublished -> 404
    $this->business->update(['published_at' => null]);
    $res404 = $this->get('/barber-keren/booking');
    $res404->assertStatus(404);

    // 2. Suspended -> 503
    $this->business->update(['published_at' => now()]);
    $this->tenant->update(['status' => 'SUSPENDED']);
    $res503 = $this->get('/barber-keren/booking');
    $res503->assertStatus(503);
});

test('WhatsApp URL correctly formats Indonesian phone numbers with country code', function () {
    $response = $this->get('/barber-keren');

    $response->assertStatus(200);
    // 081298765432 should be transformed to 6281298765432
    $response->assertSee('https://wa.me/6281298765432');
});

test('backward compatibility route /b/{business_slug} renders booking page', function () {
    $response = $this->get('/b/barber-keren');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Public/Booking')
        ->where('business.slug', 'barber-keren')
    );
});

test('booking success and manage routes return 404 for invalid code or token', function () {
    $resSuccess = $this->get('/barber-keren/booking/success/INVALID-CODE');
    $resSuccess->assertStatus(404);

    $resManage = $this->get('/barber-keren/booking/manage/invalid-token-123');
    $resManage->assertStatus(404);
});
