<?php

namespace Tests\Feature\Landing;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('unauthenticated user is redirected to login when accessing landing builder', function () {
    $response = $this->get(route('owner.landing-builder.index'));

    $response->assertRedirect(route('login'));
});

test('owner can view landing builder with default configuration', function () {
    $env = $this->createTenantEnvironment('Studio Potong');

    $response = $this->actingAs($env['user'])
        ->get(route('owner.landing-builder.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/LandingBuilder/Index')
        ->where('business.id', $env['business']->id)
        ->where('business.name', 'Studio Potong Business')
        ->has('config.theme.primary_color')
        ->has('config.sections', 6)
        ->where('config.sections.0.id', 'hero')
        ->where('config.sections.1.id', 'services')
    );
});

test('owner can update landing configuration with reordered sections and custom theme', function () {
    $env = $this->createTenantEnvironment('Klinik Cantik');

    $payload = [
        'theme' => [
            'primary_color' => '#059669',
            'font_preset' => 'plus_jakarta',
            'banner_image_url' => 'https://example.com/clinic-cover.jpg',
        ],
        'sections' => [
            [
                'id' => 'services',
                'type' => 'services',
                'enabled' => true,
                'title' => 'Menu Treatment Kami',
                'data' => [
                    'title' => 'Menu Treatment Kami',
                    'subtitle' => 'Pilih perawatan wajah terbaik untuk Anda',
                ],
            ],
            [
                'id' => 'hero',
                'type' => 'hero',
                'enabled' => true,
                'title' => 'Hero Banner Klinik',
                'data' => [
                    'headline' => 'Klinik Estetika & Skincare',
                    'subheadline' => 'Kulit sehat bercahaya bersama dokter ahli kecantikan.',
                    'cta_text' => 'Jadwalkan Konsultasi',
                    'show_whatsapp' => true,
                    'trust_points' => [
                        'Dokter Bersertifikasi',
                        'Alat Steril Standar Medis',
                    ],
                ],
            ],
            [
                'id' => 'faq',
                'type' => 'faq',
                'enabled' => false,
                'title' => 'FAQ Disembunyikan',
                'data' => [
                    'title' => 'FAQ',
                    'subtitle' => 'Tanya Jawab',
                    'items' => [],
                ],
            ],
            [
                'id' => 'about',
                'type' => 'about',
                'enabled' => true,
                'title' => 'Tentang Klinik',
                'data' => [
                    'title' => 'Tentang Klinik Cantik',
                    'content' => 'Berpengalaman melayani perawatan kulit lebih dari 10 tahun.',
                    'show_hours' => true,
                    'show_address' => true,
                ],
            ],
            [
                'id' => 'features',
                'type' => 'features',
                'enabled' => true,
                'title' => 'Keunggulan Kami',
                'data' => [
                    'title' => 'Mengapa Klinik Cantik?',
                    'subtitle' => 'Standar internasional untuk kecantikan kulit Anda.',
                    'items' => [
                        [
                            'title' => 'Produk BPOM Teruji',
                            'desc' => 'Seluruh produk aman dan bebas merkuri.',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'contact',
                'type' => 'contact',
                'enabled' => true,
                'title' => 'Hubungi & Lokasi',
                'data' => [
                    'title' => 'Lokasi Cabang Kami',
                    'subtitle' => 'Kunjungi klinik kami di pusat kota',
                    'show_whatsapp' => true,
                    'show_phone' => true,
                    'show_address' => true,
                ],
            ],
        ],
    ];

    $response = $this->actingAs($env['user'])
        ->put(route('owner.landing-builder.update'), $payload);

    $response->assertSessionHas('success');

    $env['business']->refresh();
    $settings = $env['business']->settings;
    expect($settings)->toBeArray();
    expect($settings['landing_page']['theme']['primary_color'])->toBe('#059669');
    expect($settings['landing_page']['theme']['font_preset'])->toBe('plus_jakarta');
    expect($settings['landing_page']['sections'][0]['id'])->toBe('services');
    expect($settings['landing_page']['sections'][1]['id'])->toBe('hero');
    expect($settings['landing_page']['sections'][2]['enabled'])->toBeFalse();
});

test('strict XSS sanitization neutralizes script tags and inline event handlers', function () {
    $env = $this->createTenantEnvironment('Security Test Business');

    $payload = [
        'theme' => [
            'primary_color' => '#2563eb',
            'font_preset' => 'inter',
            'banner_image_url' => null,
        ],
        'sections' => [
            [
                'id' => 'hero',
                'type' => 'hero',
                'enabled' => true,
                'title' => '<script>alert("xss-title")</script>Hero Safe',
                'data' => [
                    'headline' => '<script>evil()</script>Judul Aman <img src="x" onerror="alert(1)">',
                    'subheadline' => '<iframe src="javascript:alert(1)"></iframe>Deskripsi tanpa script',
                    'cta_text' => '<b onclick="malicious()">Klik</b> Booking',
                    'show_whatsapp' => true,
                    'trust_points' => [
                        '<script>alert("point")</script>Point 1',
                    ],
                ],
            ],
            [
                'id' => 'services',
                'type' => 'services',
                'enabled' => true,
                'title' => 'Layanan',
                'data' => [],
            ],
            [
                'id' => 'features',
                'type' => 'features',
                'enabled' => true,
                'title' => 'Fitur',
                'data' => [],
            ],
            [
                'id' => 'about',
                'type' => 'about',
                'enabled' => true,
                'title' => 'Tentang',
                'data' => [],
            ],
            [
                'id' => 'faq',
                'type' => 'faq',
                'enabled' => true,
                'title' => 'FAQ',
                'data' => [
                    'items' => [
                        [
                            'q' => '<script>alert("faq-q")</script>Pertanyaan?',
                            'a' => '<a href="javascript:alert(2)">Jawaban Aman</a>',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'contact',
                'type' => 'contact',
                'enabled' => true,
                'title' => 'Kontak',
                'data' => [],
            ],
        ],
    ];

    $response = $this->actingAs($env['user'])
        ->put(route('owner.landing-builder.update'), $payload);

    $response->assertSessionHas('success');

    $env['business']->refresh();
    $settings = $env['business']->settings;
    $rawSettingsJson = json_encode($settings);

    // Verify raw malicious strings were stripped
    expect($rawSettingsJson)->not->toContain('<script>');
    expect($rawSettingsJson)->not->toContain('onerror=');
    expect($rawSettingsJson)->not->toContain('javascript:');
    expect($rawSettingsJson)->not->toContain('<iframe>');
});

test('validation rejects invalid primary color and font preset', function () {
    $env = $this->createTenantEnvironment('Validation Test');

    $payload = [
        'theme' => [
            'primary_color' => 'invalid-color',
            'font_preset' => 'comic_sans',
        ],
        'sections' => [
            [
                'id' => 'hero',
                'type' => 'hero',
                'enabled' => true,
                'title' => 'Hero',
                'data' => [],
            ],
        ],
    ];

    $response = $this->actingAs($env['user'])
        ->put(route('owner.landing-builder.update'), $payload);

    $response->assertSessionHasErrors(['theme.primary_color', 'theme.font_preset']);
});

test('owner can toggle publish status to make public page live or 404', function () {
    $env = $this->createTenantEnvironment('Barber Live');
    $slug = $env['business']->slug;

    // Ensure business is initially published
    $this->assertNotNull($env['business']->published_at);
    $this->get("/{$slug}")->assertStatus(200);

    // Unpublish
    $unpublishResp = $this->actingAs($env['user'])
        ->post(route('owner.landing-builder.publish'), ['publish' => false]);
    $unpublishResp->assertSessionHas('success');

    $env['business']->refresh();
    expect($env['business']->published_at)->toBeNull();

    // Public page now returns 404
    $this->get("/{$slug}")->assertStatus(404);

    // Re-publish
    $publishResp = $this->actingAs($env['user'])
        ->post(route('owner.landing-builder.publish'), ['publish' => true]);
    $publishResp->assertSessionHas('success');

    $env['business']->refresh();
    expect($env['business']->published_at)->not->toBeNull();

    // Public page is 200 again
    $this->get("/{$slug}")->assertStatus(200);
});

test('owner can preview landing page in iframe even when business is not published', function () {
    $env = $this->createTenantEnvironment('Salon Privat');
    $env['business']->update(['published_at' => null]);

    // Public route returns 404
    $this->get("/{$env['business']->slug}")->assertStatus(404);

    // Preview route returns 200 with preview banner for owner
    $previewResp = $this->actingAs($env['user'])
        ->get(route('owner.landing-builder.preview'));

    $previewResp->assertStatus(200);
    $previewResp->assertSee('Mode Pratinjau Pemilik');
    $previewResp->assertSee('Salon Privat Business');
});

test('public and preview pages render customized section order and omit disabled sections', function () {
    $env = $this->createTenantEnvironment('Custom Order Salon');

    // Create 7 operating days
    for ($day = 0; $day <= 6; $day++) {
        BusinessHour::create([
            'tenant_id' => $env['tenant']->id,
            'business_id' => $env['business']->id,
            'day_of_week' => $day,
            'is_open' => true,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
            'breaks' => [],
        ]);
    }

    Service::create([
        'tenant_id' => $env['tenant']->id,
        'business_id' => $env['business']->id,
        'name' => 'Hair Treatment Premium',
        'duration_minutes' => 60,
        'price_idr' => 250000,
        'capacity' => 1,
        'is_active' => true,
        'description' => 'Perawatan rambut mendalam dengan serum alami.',
    ]);

    // Save custom config: Services first, FAQ disabled
    $payload = [
        'theme' => [
            'primary_color' => '#7c3aed',
            'font_preset' => 'plus_jakarta',
            'banner_image_url' => null,
        ],
        'sections' => [
            [
                'id' => 'services',
                'type' => 'services',
                'enabled' => true,
                'title' => 'Menu Layanan Unggulan',
                'data' => [
                    'title' => 'Menu Layanan Unggulan',
                    'subtitle' => 'Daftar paket terbaik kami',
                ],
            ],
            [
                'id' => 'hero',
                'type' => 'hero',
                'enabled' => true,
                'title' => 'Hero',
                'data' => [
                    'headline' => 'Salon Kecantikan Modern',
                    'subheadline' => 'Pilihan cerdas untuk perawatan diri.',
                    'cta_text' => 'Booking Sekarang',
                    'show_whatsapp' => true,
                    'trust_points' => ['Higienis & Nyaman'],
                ],
            ],
            [
                'id' => 'faq',
                'type' => 'faq',
                'enabled' => false,
                'title' => 'FAQ Disembunyikan',
                'data' => [
                    'title' => 'Pertanyaan yang Sengaja Dimatikan',
                ],
            ],
            [
                'id' => 'about',
                'type' => 'about',
                'enabled' => true,
                'title' => 'Tentang Kami',
                'data' => [
                    'title' => 'Cerita Salon Kami',
                    'content' => 'Melayani dengan hati dan dedikasi.',
                    'show_hours' => true,
                    'show_address' => true,
                ],
            ],
            [
                'id' => 'features',
                'type' => 'features',
                'enabled' => false,
                'title' => 'Fitur Mati',
                'data' => [],
            ],
            [
                'id' => 'contact',
                'type' => 'contact',
                'enabled' => true,
                'title' => 'Kontak',
                'data' => [
                    'title' => 'Hubungi Lokasi',
                    'subtitle' => 'Cabang utama',
                    'show_whatsapp' => true,
                    'show_phone' => true,
                    'show_address' => true,
                ],
            ],
        ],
    ];

    $this->actingAs($env['user'])
        ->put(route('owner.landing-builder.update'), $payload)
        ->assertSessionHas('success');

    // Test preview page renders CSS variable and section ordering
    $preview = $this->actingAs($env['user'])
        ->get(route('owner.landing-builder.preview'));

    $preview->assertStatus(200);
    $preview->assertSee('--color-primary: #7c3aed', false);
    $preview->assertSee('Menu Layanan Unggulan');
    $preview->assertSee('Salon Kecantikan Modern');
    $preview->assertDontSee('Pertanyaan yang Sengaja Dimatikan');

    // Test public page also reflects the saved config
    $publicResp = $this->get("/{$env['business']->slug}");
    $publicResp->assertStatus(200);
    $publicResp->assertSee('--color-primary: #7c3aed', false);
    $publicResp->assertSee('Menu Layanan Unggulan');
    $publicResp->assertDontSee('Pertanyaan yang Sengaja Dimatikan');
});

test('owner can reset landing page configuration to defaults', function () {
    $env = $this->createTenantEnvironment('Reset Test');

    // First save customized config
    $env['business']->update([
        'settings' => [
            'landing_page' => [
                'theme' => ['primary_color' => '#112233', 'font_preset' => 'plus_jakarta'],
                'sections' => [],
            ],
        ],
    ]);

    expect($env['business']->fresh()->settings['landing_page']['theme']['primary_color'])->toBe('#112233');

    // Reset defaults
    $response = $this->actingAs($env['user'])
        ->post(route('owner.landing-builder.reset-defaults'));

    $response->assertSessionHas('success');

    $env['business']->refresh();
    expect($env['business']->settings['landing_page'] ?? null)->toBeNull();
});

test('tenant isolation ensures owner cannot modify another tenants landing page', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $envB = $this->createTenantEnvironment('Tenant Beta');

    // Tenant A attempts to update landing builder
    $payload = [
        'theme' => [
            'primary_color' => '#e11d48',
            'font_preset' => 'inter',
            'banner_image_url' => null,
        ],
        'sections' => [
            [
                'id' => 'hero',
                'type' => 'hero',
                'enabled' => true,
                'title' => 'Tenant Alpha Hero',
                'data' => [
                    'headline' => 'Alpha Headline',
                ],
            ],
        ],
    ];

    $this->actingAs($envA['user'])
        ->put(route('owner.landing-builder.update'), $payload)
        ->assertSessionHas('success');

    // Ensure Tenant A got updated
    $envA['business']->refresh();
    expect($envA['business']->settings['landing_page']['theme']['primary_color'])->toBe('#e11d48');

    // Ensure Tenant B was untouched
    $envB['business']->refresh();
    expect($envB['business']->settings['landing_page'] ?? null)->toBeNull();
});
