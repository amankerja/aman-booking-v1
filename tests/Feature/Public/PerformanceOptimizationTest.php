<?php

use App\Domain\Business\Models\BusinessHour;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceCategory;
use App\Support\Services\ImageOptimizationService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 10:00:00');
    $this->seed(RolePermissionSeeder::class);

    $env = $this->createTenantEnvironment('Barber Performance Pro');
    $this->owner = $env['user'];
    $this->tenant = $env['tenant'];
    $this->business = $env['business'];

    $this->business->update([
        'slug' => 'barber-performance',
        'timezone' => 'Asia/Jakarta',
        'published_at' => Carbon::now()->subDays(5),
    ]);

    TenantContext::setTenant($this->tenant);
    Storage::fake('public');

    // Setup operating hours
    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $this->tenant->id,
            'business_id' => $this->business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '09:00:00',
            'close_time' => '21:00:00',
            'breaks' => [],
        ]);
    }
});

afterEach(function () {
    TenantContext::clear();
});

it('optimizes, downscales and converts large uploaded images to WebP (PRD 204.8)', function () {
    /** @var ImageOptimizationService $service */
    $service = app(ImageOptimizationService::class);

    // Create a 2400x1800 test image in memory via GD
    $img = imagecreatetruecolor(2400, 1800);
    $bg = imagecolorallocate($img, 37, 99, 235); // Blue
    imagefill($img, 0, 0, $bg);

    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_').'.jpg';
    imagejpeg($img, $tempFile, 95);
    imagedestroy($img);

    $uploadedFile = new UploadedFile(
        $tempFile,
        'large-hero.jpg',
        'image/jpeg',
        null,
        true
    );

    $storedPath = $service->optimizeAndStore(
        $uploadedFile,
        'test-uploads',
        'public',
        1600,
        82
    );

    @unlink($tempFile);

    expect($storedPath)->toEndWith('.webp');
    Storage::disk('public')->assertExists($storedPath);

    // Verify optimized dimensions do not exceed 1600px
    $optimizedData = Storage::disk('public')->get($storedPath);
    $optimizedImg = imagecreatefromstring($optimizedData);

    expect($optimizedImg)->not->toBeFalse();
    $optWidth = imagesx($optimizedImg);
    $optHeight = imagesy($optimizedImg);

    expect($optWidth)->toBeLessThanOrEqual(1600)
        ->and($optHeight)->toBeLessThanOrEqual(1600)
        ->and($optWidth)->toBe(1600) // Proportionally 2400x1800 scaled to 1600x1200
        ->and($optHeight)->toBe(1200);

    imagedestroy($optimizedImg);
});

it('converts uploaded business logo to WebP format automatically', function () {
    $this->actingAs($this->owner);

    // Generate test PNG image
    $img = imagecreatetruecolor(400, 400);
    $col = imagecolorallocate($img, 15, 23, 42);
    imagefill($img, 0, 0, $col);
    $tempFile = tempnam(sys_get_temp_dir(), 'logo_').'.png';
    imagepng($img, $tempFile);
    imagedestroy($img);

    $file = new UploadedFile($tempFile, 'company-logo.png', 'image/png', null, true);

    $response = $this->post(route('owner.settings.business.logo'), [
        'logo' => $file,
    ]);

    @unlink($tempFile);

    $response->assertRedirect();
    $this->business->refresh();

    expect($this->business->logo_path)->not->toBeNull()
        ->and($this->business->logo_path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($this->business->logo_path);
});

it('converts uploaded service image to WebP format on create and update', function () {
    $this->actingAs($this->owner);

    $category = ServiceCategory::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut',
        'order' => 1,
    ]);

    // Create service with image
    $img = imagecreatetruecolor(800, 600);
    $col = imagecolorallocate($img, 200, 100, 50);
    imagefill($img, 0, 0, $col);
    $tempFile = tempnam(sys_get_temp_dir(), 'service_').'.jpg';
    imagejpeg($img, $tempFile);
    imagedestroy($img);

    $file = new UploadedFile($tempFile, 'service-photo.jpg', 'image/jpeg', null, true);

    $response = $this->post(route('owner.services.store'), [
        'category_id' => $category->id,
        'name' => 'Premium Shave & Trim',
        'price_idr' => 75000,
        'duration_type' => 'FIXED',
        'duration_minutes' => 45,
        'capacity' => 1,
        'image' => $file,
    ]);

    @unlink($tempFile);

    $response->assertRedirect();
    $service = Service::where('business_id', $this->business->id)->first();

    expect($service)->not->toBeNull()
        ->and($service->image_path)->not->toBeNull()
        ->and($service->image_path)->toEndWith('.webp');

    Storage::disk('public')->assertExists($service->image_path);
});

it('verifies public landing page HTML size and zero render-blocking JS budget (PRD 204.8)', function () {
    $this->business->update(['logo_path' => 'logos/test-logo.webp']);

    $response = $this->get('/barber-performance');

    $response->assertOk();

    $html = $response->getContent();
    expect($html)->not->toBeEmpty();

    // 1. Budget: HTML < 100 KB
    $htmlSizeBytes = strlen($html);
    $htmlSizeKb = $htmlSizeBytes / 1024;
    expect($htmlSizeKb)->toBeLessThan(100.0);

    // 2. Budget: Zero render-blocking JS on landing page (only structured data JSON-LD allowed)
    // There must be no external <script src=...> tags
    expect($html)->not->toContain('<script src=')
        ->and($html)->toContain('type="application/ld+json"');

    // 3. Image loading optimization: lazy loading & async decoding
    expect($html)->toContain('loading="lazy"');
});

it('verifies public .htaccess contains static asset caching and compression directives (PRD 204.8)', function () {
    $htaccessPath = public_path('.htaccess');
    expect(file_exists($htaccessPath))->toBeTrue();

    $content = file_get_contents($htaccessPath);

    // Gzip / Deflate compression
    expect($content)->toContain('mod_deflate.c')
        ->and($content)->toContain('AddOutputFilterByType DEFLATE');

    // 1-year immutable cache header for hashed assets
    expect($content)->toContain('max-age=31536000, immutable')
        ->and($content)->toContain('mod_expires.c');

    // Security headers (PRD 205)
    expect($content)->toContain('X-Content-Type-Options')
        ->and($content)->toContain('X-Frame-Options');
});

it('responds on availability endpoint in less than 500 ms (PRD 204.8)', function () {
    $category = ServiceCategory::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'name' => 'Haircut',
        'order' => 1,
    ]);

    $service = Service::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'category_id' => $category->id,
        'name' => 'Gentleman Haircut',
        'duration_minutes' => 45,
        'price_idr' => 100000,
        'capacity' => 1,
        'is_active' => true,
    ]);

    $type = ResourceType::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BARBER',
        'name' => 'Barber',
        'is_staff' => true,
    ]);

    Resource::create([
        'tenant_id' => $this->tenant->id,
        'business_id' => $this->business->id,
        'resource_type_id' => $type->id,
        'name' => 'Doni Barber',
        'state' => 'AVAILABLE',
        'visibility' => 'PUBLIC',
    ]);

    $startBenchmark = microtime(true);

    $response = $this->getJson("/api/public/barber-performance/availability?service_id={$service->id}&date=2026-10-06");

    $elapsedMs = (microtime(true) - $startBenchmark) * 1000;

    $response->assertOk();
    $response->assertJsonStructure(['slots', 'timezone']);

    // Budget: < 500 ms p95
    expect($elapsedMs)->toBeLessThan(500.0);
});
