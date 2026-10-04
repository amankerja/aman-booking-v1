<?php

namespace Tests\Feature\Business;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Business\Models\Business;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class);
uses(TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
    Storage::fake('public');
});

afterEach(function () {
    TenantContext::clear();
});

test('owner can view business profile settings page', function () {
    $env = $this->createTenantEnvironment('Barber Shop');

    $response = $this->actingAs($env['user'])->get(route('owner.settings.business'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Settings/BusinessProfile')
        ->where('business.id', $env['business']->id)
        ->where('business.name', 'Barber Shop Business')
        ->where('business.timezone', 'Asia/Jakarta')
        ->has('timezones', 3)
    );
});

test('owner can update business profile details and policies', function () {
    $env = $this->createTenantEnvironment('Klinik Sehat');

    $payload = [
        'name' => 'Klinik Sehat Utama',
        'slug' => 'klinik-sehat-utama',
        'timezone' => 'Asia/Makassar',
        'whatsapp' => '081234567890',
        'phone' => '021123456',
        'email' => 'contact@kliniksehat.com',
        'address' => 'Jl. Boulevard No. 12',
        'city' => 'Makassar',
        'province' => 'Sulawesi Selatan',
        'postal_code' => '90123',
        'description' => 'Klinik estetika dan kesehatan terpercaya.',
        'policies' => [
            'booking_policy' => 'Harap hadir 10 menit sebelum jadwal.',
            'cancellation_policy' => 'Pembatalan maksimal H-1.',
            'refund_policy' => 'Refund 100% jika dibatalkan H-1.',
            'reschedule_policy' => 'Reschedule maksimal 2 kali.',
        ],
        'booking_rules' => [
            'min_advance_minutes' => 60,
            'max_advance_days' => 14,
            'allow_same_day' => true,
            'cancellation_deadline_hours' => 24,
            'reschedule_deadline_hours' => 12,
            'max_reschedule_times' => 2,
        ],
        'social_links' => [
            'instagram' => 'kliniksehat.id',
            'facebook' => 'kliniksehatfb',
            'website' => 'https://kliniksehat.com',
            'tiktok' => 'kliniksehattiktok',
        ],
    ];

    $response = $this->actingAs($env['user'])->put(route('owner.settings.business.update'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $updated = Business::withoutGlobalScopes()->find($env['business']->id);
    expect($updated->name)->toBe('Klinik Sehat Utama')
        ->and($updated->slug)->toBe('klinik-sehat-utama')
        ->and($updated->timezone)->toBe('Asia/Makassar')
        ->and($updated->whatsapp)->toBe('081234567890')
        ->and($updated->city)->toBe('Makassar')
        ->and($updated->booking_rules['min_advance_minutes'])->toBe(60)
        ->and($updated->policies['booking_policy'])->toBe('Harap hadir 10 menit sebelum jadwal.');

    // Verify audit log
    $auditLog = AuditLog::where('action', 'business.updated')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id)
        ->and($auditLog->after['name'])->toBe('Klinik Sehat Utama');
});

test('validation rejects duplicate business slug from another tenant', function () {
    $env1 = $this->createTenantEnvironment('Tenant Satu');
    $env2 = $this->createTenantEnvironment('Tenant Dua');

    $response = $this->actingAs($env1['user'])->put(route('owner.settings.business.update'), [
        'name' => 'Tenant Satu Rename',
        'slug' => $env2['business']->slug, // duplicate slug from Tenant Dua
        'timezone' => 'Asia/Jakarta',
    ]);

    $response->assertSessionHasErrors(['slug']);
});

test('validation rejects invalid timezone value', function () {
    $env = $this->createTenantEnvironment('Tenant Alpha');

    $response = $this->actingAs($env['user'])->put(route('owner.settings.business.update'), [
        'name' => 'Tenant Alpha',
        'slug' => 'tenant-alpha',
        'timezone' => 'America/New_York', // unsupported timezone
    ]);

    $response->assertSessionHasErrors(['timezone']);
});

test('owner can upload and replace business logo', function () {
    $env = $this->createTenantEnvironment('Studio Foto');

    $file = UploadedFile::fake()->image('logo.png', 400, 400)->size(500);

    $response = $this->actingAs($env['user'])->post(route('owner.settings.business.logo'), [
        'logo' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $business = Business::withoutGlobalScopes()->find($env['business']->id);
    expect($business->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($business->logo_path);

    // Audit log
    $auditLog = AuditLog::where('action', 'business.logo_uploaded')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id);
});

test('logo upload rejects non-image file and files exceeding max size', function () {
    $env = $this->createTenantEnvironment('Studio Foto');

    $invalidFile = UploadedFile::fake()->create('document.pdf', 300, 'application/pdf');

    $response = $this->actingAs($env['user'])->post(route('owner.settings.business.logo'), [
        'logo' => $invalidFile,
    ]);

    $response->assertSessionHasErrors(['logo']);

    $tooLargeFile = UploadedFile::fake()->image('huge_photo.jpg')->size(3000); // 3MB > 2MB limit

    $responseLarge = $this->actingAs($env['user'])->post(route('owner.settings.business.logo'), [
        'logo' => $tooLargeFile,
    ]);

    $responseLarge->assertSessionHasErrors(['logo']);
});

test('owner can remove business logo', function () {
    $env = $this->createTenantEnvironment('Studio Musik');

    // First upload a logo
    $file = UploadedFile::fake()->image('old_logo.png');
    $this->actingAs($env['user'])->post(route('owner.settings.business.logo'), ['logo' => $file]);

    $business = Business::withoutGlobalScopes()->find($env['business']->id);
    $oldPath = $business->logo_path;
    Storage::disk('public')->assertExists($oldPath);

    // Remove logo
    $response = $this->actingAs($env['user'])->delete(route('owner.settings.business.logo.remove'));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $business->refresh();
    expect($business->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);

    // Audit log
    $auditLog = AuditLog::where('action', 'business.logo_removed')->latest()->first();
    expect($auditLog)->not->toBeNull()
        ->and($auditLog->tenant_id)->toBe($env['tenant']->id);
});
