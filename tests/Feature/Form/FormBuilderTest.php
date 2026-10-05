<?php

namespace Tests\Feature\Form;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingCustomField;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Form\Services\FormService;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
    Storage::fake('local');
});

afterEach(function () {
    TenantContext::clear();
});

test('it auto-seeds standard default form for a tenant if none exists (PRD 26, 179)', function () {
    $env = $this->createTenantEnvironment('Rental Kita');
    $tenant = $env['tenant'];
    $owner = $env['user'];

    $response = $this->actingAs($owner)->get(route('owner.settings.forms.index'));
    $response->assertOk();

    $this->assertDatabaseHas('booking_forms', [
        'tenant_id' => $tenant->id,
        'is_default' => true,
    ]);

    $form = BookingForm::where('tenant_id', $tenant->id)->first();
    expect($form)->not->toBeNull();
    expect($form->fields()->count())->toBeGreaterThan(0);
});

test('owner can create a new form and bind to specific service', function () {
    $env = $this->createTenantEnvironment('Auto Workshop');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Ganti Oli & Tune Up',
    ]);

    $response = $this->actingAs($owner)->post(route('owner.settings.forms.store'), [
        'name' => 'Formulir Khusus Servis Mobil',
        'description' => 'Formulir pengecekan keluhan mesin',
        'service_id' => $service->id,
        'is_default' => false,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('booking_forms', [
        'tenant_id' => $tenant->id,
        'name' => 'Formulir Khusus Servis Mobil',
        'service_id' => $service->id,
    ]);
});

test('owner can add, update, reorder and delete fields on a form', function () {
    $env = $this->createTenantEnvironment('Pet Care Hub');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $form = BookingForm::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Formulir Grooming',
    ]);

    // 1. Add field
    $addResp = $this->actingAs($owner)->post(route('owner.settings.forms.fields.store', ['id' => $form->id]), [
        'field_key' => 'pet_weight_kg',
        'type' => 'number',
        'label' => 'Berat Hewan (KG)',
        'placeholder' => 'Contoh: 5',
        'is_required' => true,
    ]);
    $addResp->assertRedirect();

    $this->assertDatabaseHas('booking_form_fields', [
        'form_id' => $form->id,
        'field_key' => 'pet_weight_kg',
        'is_required' => true,
    ]);

    $field = BookingFormField::where('form_id', $form->id)->where('field_key', 'pet_weight_kg')->firstOrFail();

    // 2. Update field
    $upResp = $this->actingAs($owner)->put(
        route('owner.settings.forms.fields.update', ['id' => $form->id, 'fieldId' => $field->id]),
        [
            'label' => 'Berat Badan Hewan Peliharaan (KG)',
            'placeholder' => '5.5',
            'is_required' => false,
            'is_active' => true,
        ]
    );
    $upResp->assertRedirect();

    expect($field->fresh()->label)->toBe('Berat Badan Hewan Peliharaan (KG)');
    expect($field->fresh()->is_required)->toBeFalse();

    // 3. Add second field and reorder
    $field2 = BookingFormField::factory()->create([
        'tenant_id' => $tenant->id,
        'form_id' => $form->id,
        'field_key' => 'rabies_vaccinated',
        'sort_order' => 2,
    ]);

    $reorderResp = $this->actingAs($owner)->post(
        route('owner.settings.forms.fields.reorder', ['id' => $form->id]),
        [
            'fields' => [
                ['id' => $field2->id, 'sort_order' => 1],
                ['id' => $field->id, 'sort_order' => 2],
            ],
        ]
    );
    $reorderResp->assertRedirect();

    expect($field2->fresh()->sort_order)->toBe(1);
    expect($field->fresh()->sort_order)->toBe(2);

    // 4. Delete field
    $delResp = $this->actingAs($owner)->delete(
        route('owner.settings.forms.fields.destroy', ['id' => $form->id, 'fieldId' => $field->id])
    );
    $delResp->assertRedirect();

    $this->assertDatabaseMissing('booking_form_fields', ['id' => $field->id]);
});

test('it installs business preset templates with conditional logic (PRD 179)', function () {
    $env = $this->createTenantEnvironment('Bali Rent Car');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->post(route('owner.settings.forms.install-preset'), [
        'preset' => 'rental',
    ]);
    $response->assertRedirect();

    $form = BookingForm::where('tenant_id', $tenant->id)
        ->where('name', 'Formulir Rental Kendaraan')
        ->with('fields')
        ->first();

    expect($form)->not->toBeNull();
    $fieldKeys = $form->fields->pluck('field_key')->all();
    expect($fieldKeys)->toContain('pickup_mode', 'hotel_name', 'room_number', 'id_card_file');

    // Verify hotel_name has condition depending on pickup_mode == hotel
    $hotelField = $form->fields->firstWhere('field_key', 'hotel_name');
    expect($hotelField->visibility_conditions)->toBe([
        'field' => 'pickup_mode',
        'operator' => 'eq',
        'value' => 'hotel',
    ]);
});

test('server-side conditional evaluation skips hidden required fields (PRD 3.2)', function () {
    $env = $this->createTenantEnvironment('Luxury Rental');
    $tenant = $env['tenant'];

    $formService = app(FormService::class);
    $form = $formService->seedDefaultFormForTenant($tenant, 'rental');

    // Case 1: pickup_mode is 'office' (Garasi) -> hotel_name is HIDDEN.
    // Even though hotel_name has is_required=true, it MUST NOT fail validation!
    $submittedData = [
        'pickup_mode' => 'office',
        // hotel_name is not provided!
    ];

    $validated = $formService->validateAndSanitizeSubmission($form, $submittedData);
    $keys = collect($validated)->pluck('field_key')->all();

    expect($keys)->toContain('pickup_mode');
    expect($keys)->not->toContain('hotel_name');

    // Case 2: pickup_mode is 'hotel' -> hotel_name is VISIBLE and REQUIRED.
    // Omitting hotel_name MUST throw validation failed BookingException.
    $submittedDataWithHotel = [
        'pickup_mode' => 'hotel',
        // hotel_name is missing
    ];

    expect(fn () => $formService->validateAndSanitizeSubmission($form, $submittedDataWithHotel))
        ->toThrow(BookingException::class, "Kolom 'Nama Hotel / Penginapan' wajib diisi.");

    // Case 3: pickup_mode is 'hotel' and hotel_name is filled -> passes
    $validData = [
        'pickup_mode' => 'hotel',
        'hotel_name' => 'Grand Hyatt Bali',
    ];

    $result = $formService->validateAndSanitizeSubmission($form, $validData);
    $validatedKeys = collect($result)->pluck('field_key')->all();
    expect($validatedKeys)->toContain('pickup_mode', 'hotel_name');
});

test('secure file upload validates MIME types, blocks dangerous extensions and randomizes filenames', function () {
    $env = $this->createTenantEnvironment('Photo Studio');
    $tenant = $env['tenant'];

    $form = BookingForm::factory()->create(['tenant_id' => $tenant->id]);
    $fileField = BookingFormField::factory()->create([
        'tenant_id' => $tenant->id,
        'form_id' => $form->id,
        'field_key' => 'id_card',
        'type' => 'file',
        'label' => 'KTP Pengemudi',
        'is_required' => true,
    ]);

    $formService = app(FormService::class);

    // 1. Safe file (image/jpeg)
    $safeFile = UploadedFile::fake()->create('my_ktp.jpg', 200, 'image/jpeg');
    $validated = $formService->validateAndSanitizeSubmission(
        $form,
        [],
        ['id_card' => $safeFile]
    );

    expect($validated)->toHaveCount(1);
    expect($validated[0]['field_key'])->toBe('id_card');
    $storedPath = $validated[0]['value_text'];
    expect($storedPath)->toStartWith("tenant_uploads/{$tenant->id}/");
    expect($storedPath)->not->toContain('my_ktp.jpg'); // Random filename
    Storage::disk('local')->assertExists($storedPath);

    // 2. Dangerous file (.php or .exe)
    $dangerousPhp = UploadedFile::fake()->create('malicious.php', 10, 'text/x-php');
    expect(fn () => $formService->validateAndSanitizeSubmission($form, [], ['id_card' => $dangerousPhp]))
        ->toThrow(BookingException::class, 'tidak diizinkan demi keamanan');

    $dangerousExe = UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload');
    expect(fn () => $formService->validateAndSanitizeSubmission($form, [], ['id_card' => $dangerousExe]))
        ->toThrow(BookingException::class, 'tidak diizinkan demi keamanan');
});

test('public booking submission stores custom fields into database linked to booking', function () {
    $env = $this->createTenantEnvironment('Bintang Futsal');
    $tenant = $env['tenant'];
    $business = $env['business'];

    // Seed business operating hours for every day
    for ($d = 0; $d <= 6; $d++) {
        \App\Domain\Business\Models\BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '07:00:00',
            'close_time' => '23:00:00',
            'breaks' => [],
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Lapangan Futsal Vinyl',
        'duration_minutes' => 60,
    ]);

    $formService = app(FormService::class);
    $form = $formService->seedDefaultFormForTenant($tenant, 'sports');

    $startAt = \Carbon\Carbon::now('Asia/Jakarta')->addDays(2)->setTime(10, 0, 0)->toIso8601String();

    $response = $this->postJson(route('public.booking.store', ['slug' => $business->slug]), [
        'service_id' => $service->id,
        'start_at' => $startAt,
        'customer_name' => 'Kapten Tsubasa',
        'customer_phone' => '081234567890',
        'customer_email' => 'tsubasa@nankatsu.com',
        'custom_fields' => [
            'team_name' => 'Nankatsu SC',
            'number_of_players' => 10,
            'need_referee' => 'yes',
        ],
    ]);

    $response->assertOk();

    $booking = Booking::where('tenant_id', $tenant->id)->firstOrFail();
    $customFields = BookingCustomField::where('booking_id', $booking->id)->get();

    expect($customFields->count())->toBeGreaterThanOrEqual(3);
    $teamField = $customFields->firstWhere('field_key', 'team_name');
    expect($teamField)->not->toBeNull();
    expect($teamField->value_text)->toBe('Nankatsu SC');
});

test('tenant B cannot access or download custom files from tenant A', function () {
    $envA = $this->createTenantEnvironment('Tenant Alfa');
    $tenantA = $envA['tenant'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    $ownerB = $envB['user'];

    $serviceA = Service::factory()->create(['tenant_id' => $tenantA->id]);
    $bookingA = Booking::factory()->create([
        'tenant_id' => $tenantA->id,
        'service_id' => $serviceA->id,
    ]);

    $fakeFile = UploadedFile::fake()->create('contract.pdf', 150, 'application/pdf');
    $path = $fakeFile->store("tenant_uploads/{$tenantA->id}", 'local');

    $customFieldA = BookingCustomField::create([
        'tenant_id' => $tenantA->id,
        'booking_id' => $bookingA->id,
        'field_key' => 'contract',
        'field_label' => 'Surat Kontrak',
        'field_type' => 'file',
        'value_text' => $path,
    ]);

    // Owner B attempts to download tenant A's file
    $response = $this->actingAs($ownerB)->get(route('owner.booking-files.download', ['id' => $customFieldA->id]));
    $response->assertNotFound();
});

test('owner quick booking validates and saves custom fields (PRD 3.2)', function () {
    $env = $this->createTenantEnvironment('Rental Mobil Kilat');
    $tenant = $env['tenant'];
    $owner = $env['user'];
    $business = $env['business'];

    for ($d = 0; $d <= 6; $d++) {
        \App\Domain\Business\Models\BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'day_of_week' => $d,
            'is_open' => true,
            'open_time' => '07:00:00',
            'close_time' => '23:00:00',
            'breaks' => [],
        ]);
    }

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'business_id' => $business->id,
        'name' => 'Sewa Avanza Harian',
        'duration_minutes' => 120,
    ]);

    $formService = app(FormService::class);
    $form = $formService->seedDefaultFormForTenant($tenant, 'rental');

    BookingFormField::factory()->create([
        'tenant_id' => $tenant->id,
        'form_id' => $form->id,
        'field_key' => 'driving_license_number',
        'type' => 'text',
        'label' => 'Nomor SIM Pengemudi',
        'is_required' => true,
    ]);

    $startAt = \Carbon\Carbon::now('Asia/Jakarta')->addDays(1)->setTime(10, 0, 0)->toIso8601String();

    $response = $this->actingAs($owner)->postJson('/app/bookings', [
        'service_id' => $service->id,
        'start_at' => $startAt,
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '081298765432',
        'customer_email' => 'budi@example.com',
        'payment_status' => 'PAID',
        'notes' => 'Mobil bersih dan wangi',
        'custom_fields' => [
            'pickup_mode' => 'office',
            'driving_license_number' => 'SIM-1234567890',
        ],
    ]);

    $response->assertStatus(201);

    $booking = Booking::where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
    $customFields = BookingCustomField::where('booking_id', $booking->id)->get();

    expect($customFields->count())->toBeGreaterThanOrEqual(2);
    $pickupField = $customFields->firstWhere('field_key', 'pickup_mode');
    expect($pickupField)->not->toBeNull();
    expect($pickupField->value_text)->toBe('office');

    $licenseField = $customFields->firstWhere('field_key', 'driving_license_number');
    expect($licenseField)->not->toBeNull();
    expect($licenseField->value_text)->toBe('SIM-1234567890');
});

