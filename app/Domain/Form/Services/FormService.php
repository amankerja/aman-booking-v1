<?php

namespace App\Domain\Form\Services;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingCustomField;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FormService
{
    /**
     * Dangerous file extensions that must always be blocked.
     *
     * @var array<int, string>
     */
    protected static array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat',
        'cmd', 'cgi', 'pl', 'py', 'js', 'html', 'htm', 'svg', 'vbs', 'jar',
    ];

    /**
     * Safe allowed MIME types for uploaded files.
     *
     * @var array<int, string>
     */
    protected static array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Get the active form for a service or default for a tenant.
     */
    public function getFormForService(int $tenantId, ?int $serviceId = null): ?BookingForm
    {
        // 1. Look for form bound to this specific service
        if ($serviceId) {
            $serviceForm = BookingForm::where('tenant_id', $tenantId)
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->with(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order', 'asc')])
                ->first();

            if ($serviceForm) {
                return $serviceForm;
            }
        }

        // 2. Look for tenant default form
        $defaultForm = BookingForm::where('tenant_id', $tenantId)
            ->whereNull('service_id')
            ->where('is_active', true)
            ->where('is_default', true)
            ->with(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order', 'asc')])
            ->first();

        if ($defaultForm) {
            return $defaultForm;
        }

        // 3. Fallback: Any active form
        $anyForm = BookingForm::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['fields' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order', 'asc')])
            ->first();

        if ($anyForm) {
            return $anyForm;
        }

        // 4. If tenant has no form at all, seed standard default form
        $tenant = Tenant::find($tenantId);
        if ($tenant) {
            return $this->seedDefaultFormForTenant($tenant);
        }

        return null;
    }

    /**
     * Evaluate field visibility conditions against submitted data on SERVER.
     *
     * @param  array<string, mixed>  $submittedData
     */
    public function evaluateVisibility(BookingFormField $field, array $submittedData): bool
    {
        $conditions = $field->visibility_conditions;

        if (empty($conditions)) {
            return true;
        }

        // Support single condition object or array of conditions
        $ruleList = (isset($conditions['field']) || isset($conditions['field_key'])) ? [$conditions] : (array) $conditions;

        foreach ($ruleList as $rule) {
            $targetKey = $rule['field'] ?? $rule['field_key'] ?? null;
            $operator = $rule['operator'] ?? 'eq';
            $expectedValue = $rule['value'] ?? null;

            if (! $targetKey) {
                continue;
            }

            $actualValue = $submittedData[$targetKey] ?? null;

            $conditionMet = match ($operator) {
                'eq', 'equals' => (string) $actualValue === (string) $expectedValue,
                'neq', 'not_equals' => (string) $actualValue !== (string) $expectedValue,
                'contains' => str_contains(strtolower((string) $actualValue), strtolower((string) $expectedValue)),
                'in' => in_array($actualValue, (array) $expectedValue, false),
                'not_in' => ! in_array($actualValue, (array) $expectedValue, false),
                'filled', 'is_not_empty' => ! empty($actualValue),
                'empty', 'is_empty' => empty($actualValue),
                default => (string) $actualValue === (string) $expectedValue,
            };

            // All conditions must pass (AND logic)
            if (! $conditionMet) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate and sanitize submitted custom fields with server-side conditional evaluation (PRD 3.2).
     *
     * @param  array<string, mixed>  $submittedData
     * @param  array<string, UploadedFile>  $uploadedFiles
     * @return array<int, array{
     *     field_key: string,
     *     field_label: string,
     *     field_type: string,
     *     value_text: string|null,
     *     value_json: array<string, mixed>|null
     * }>
     *
     * @throws BookingException
     */
    public function validateAndSanitizeSubmission(
        BookingForm $form,
        array $submittedData,
        array $uploadedFiles = []
    ): array {
        $validatedList = [];
        $fields = $form->fields()->where('is_active', true)->orderBy('sort_order', 'asc')->get();

        foreach ($fields as $field) {
            // Server-side conditional evaluation (PRD 3.2)
            $isVisible = $this->evaluateVisibility($field, $submittedData);

            // PRD 3.2: "Field wajib tersembunyi tidak divalidasi"
            if (! $isVisible) {
                continue;
            }

            $key = $field->field_key;
            $rawVal = $submittedData[$key] ?? null;
            $fileVal = $uploadedFiles[$key] ?? null;

            // Required check for visible fields
            if ($field->is_required) {
                if ($field->type === 'file') {
                    if (! $fileVal instanceof UploadedFile) {
                        throw BookingException::validationFailed("Dokumen atau berkas '{$field->label}' wajib diunggah.");
                    }
                } else {
                    if ($rawVal === null || $rawVal === '') {
                        throw BookingException::validationFailed("Kolom '{$field->label}' wajib diisi.");
                    }
                }
            }

            // Skip empty non-required fields
            if (($rawVal === null || $rawVal === '') && ! ($fileVal instanceof UploadedFile)) {
                continue;
            }

            $valueText = null;
            $valueJson = null;

            // Handle file upload security
            if ($field->type === 'file') {
                if ($fileVal instanceof UploadedFile) {
                    $storedPath = $this->handleSecureFileUpload($fileVal, $form->tenant_id, $field);
                    $valueText = $storedPath;
                }
            } elseif (in_array($field->type, ['multiselect', 'checkbox', 'address'], true)) {
                if (is_array($rawVal)) {
                    $valueJson = $rawVal;
                } else {
                    $valueText = (string) $rawVal;
                }
            } else {
                $valueText = is_scalar($rawVal) ? (string) $rawVal : json_encode($rawVal);
            }

            $validatedList[] = [
                'field_key' => $field->field_key,
                'field_label' => $field->label,
                'field_type' => $field->type,
                'value_text' => $valueText,
                'value_json' => $valueJson,
            ];
        }

        return $validatedList;
    }

    /**
     * Store uploaded file securely with extension whitelist and random filename (PRD 3.2).
     *
     * @throws BookingException
     */
    public function handleSecureFileUpload(UploadedFile $file, int $tenantId, BookingFormField $field): string
    {
        // 1. Check if upload is valid
        if (! $file->isValid()) {
            throw BookingException::validationFailed("Berkas untuk '{$field->label}' tidak valid atau gagal diunggah.");
        }

        // 2. Reject blocked dangerous extensions
        $clientExtension = strtolower($file->getClientOriginalExtension());
        if (in_array($clientExtension, self::$blockedExtensions, true)) {
            throw BookingException::validationFailed("Jenis berkas .{$clientExtension} tidak diizinkan demi keamanan.");
        }

        // 3. Inspect real MIME type
        $mime = $file->getMimeType();
        if (! in_array($mime, self::$allowedMimeTypes, true)) {
            throw BookingException::validationFailed("Format berkas ({$mime}) tidak didukung. Harap unggah format JPG, PNG, atau PDF.");
        }

        // 4. Validate file size (max 5MB / 5120 KB default or from validation_rules)
        $rules = $field->validation_rules ?? [];
        $maxKb = (int) ($rules['max_size_kb'] ?? 5120);
        $fileSizeKb = (int) ($file->getSize() / 1024);

        if ($fileSizeKb > $maxKb) {
            throw BookingException::validationFailed("Ukuran berkas melebihi batas maksimal ({$maxKb} KB).");
        }

        // 5. Store with randomized filename in private directory
        $safeExtension = $file->guessExtension() ?? $clientExtension;
        $randomFileName = Str::random(40).'.'.$safeExtension;
        $targetDirectory = "tenant_uploads/{$tenantId}";

        $storedPath = $file->storeAs($targetDirectory, $randomFileName, 'local');

        if (! $storedPath) {
            throw BookingException::validationFailed('Gagal menyimpan berkas ke penyimpanan aman.');
        }

        return $storedPath;
    }

    /**
     * Save custom field responses for a booking.
     *
     * @param  array<int, array{
     *     field_key: string,
     *     field_label: string,
     *     field_type: string,
     *     value_text: string|null,
     *     value_json: array<string, mixed>|null
     * }>  $fieldsData
     */
    public function saveCustomFields(Booking $booking, array $fieldsData): void
    {
        DB::transaction(function () use ($booking, $fieldsData) {
            foreach ($fieldsData as $data) {
                BookingCustomField::updateOrCreate(
                    [
                        'tenant_id' => $booking->tenant_id,
                        'booking_id' => $booking->id,
                        'field_key' => $data['field_key'],
                    ],
                    [
                        'field_label' => $data['field_label'],
                        'field_type' => $data['field_type'],
                        'value_text' => $data['value_text'],
                        'value_json' => $data['value_json'],
                    ]
                );
            }
        });
    }

    /**
     * Seed default template form per business type (PRD 179).
     */
    public function seedDefaultFormForTenant(Tenant $tenant, ?string $preset = null): BookingForm
    {
        $preset = $preset ?? 'custom';

        return DB::transaction(function () use ($tenant, $preset) {
            $formName = match ($preset) {
                'rental' => 'Formulir Rental Kendaraan',
                'klinik' => 'Formulir Pasien Klinik',
                'pet' => 'Formulir Pet Grooming',
                'bengkel' => 'Formulir Servis Kendaraan',
                'sports' => 'Formulir Sewa Lapangan',
                default => 'Formulir Pemesanan Standar',
            };

            $form = BookingForm::create([
                'tenant_id' => $tenant->id,
                'service_id' => null,
                'name' => $formName,
                'slug' => 'form-default-'.Str::random(6),
                'description' => "Formulir standar {$preset} dengan aturan kondisional (PRD 26, 27).",
                'is_default' => true,
                'is_active' => true,
            ]);

            $fields = match ($preset) {
                'rental' => [
                    [
                        'field_key' => 'pickup_mode',
                        'type' => 'radio',
                        'label' => 'Metode Pengambilan',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Ambil di Kantor / Garasi', 'value' => 'office'],
                            ['label' => 'Antar ke Hotel / Alamat Tamu', 'value' => 'hotel'],
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'hotel_name',
                        'type' => 'text',
                        'label' => 'Nama Hotel / Penginapan',
                        'placeholder' => 'Contoh: Hotel Mulia Senayan',
                        'is_required' => true,
                        'visibility_conditions' => [
                            'field' => 'pickup_mode',
                            'operator' => 'eq',
                            'value' => 'hotel',
                        ],
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'room_number',
                        'type' => 'text',
                        'label' => 'Nomor Kamar',
                        'placeholder' => 'Contoh: Room 402',
                        'is_required' => false,
                        'visibility_conditions' => [
                            'field' => 'pickup_mode',
                            'operator' => 'eq',
                            'value' => 'hotel',
                        ],
                        'sort_order' => 3,
                    ],
                    [
                        'field_key' => 'id_card_file',
                        'type' => 'file',
                        'label' => 'Foto KTP / SIM Pengemudi',
                        'help_text' => 'Format JPG, PNG atau PDF (Maks. 5 MB)',
                        'is_required' => false,
                        'sort_order' => 4,
                    ],
                ],
                'klinik' => [
                    [
                        'field_key' => 'complaint',
                        'type' => 'textarea',
                        'label' => 'Keluhan / Riwayat Perawatan',
                        'placeholder' => 'Jelaskan keluhan atau kondisi saat ini...',
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'allergy_check',
                        'type' => 'radio',
                        'label' => 'Apakah Memiliki Alergi Obat/Bahan?',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Tidak Ada', 'value' => 'no'],
                            ['label' => 'Ada Alergi', 'value' => 'yes'],
                        ],
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'allergy_details',
                        'type' => 'text',
                        'label' => 'Detail Alergi',
                        'placeholder' => 'Sebutkan jenis obat atau bahan yang memicu alergi...',
                        'is_required' => true,
                        'visibility_conditions' => [
                            'field' => 'allergy_check',
                            'operator' => 'eq',
                            'value' => 'yes',
                        ],
                        'sort_order' => 3,
                    ],
                ],
                'pet' => [
                    [
                        'field_key' => 'pet_name',
                        'type' => 'text',
                        'label' => 'Nama Hewan Peliharaan',
                        'placeholder' => 'Contoh: Milo / Luna',
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'pet_type',
                        'type' => 'select',
                        'label' => 'Jenis Hewan',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Anjing', 'value' => 'dog'],
                            ['label' => 'Kucing', 'value' => 'cat'],
                            ['label' => 'Kelinci / Lainnya', 'value' => 'other'],
                        ],
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'pet_breed',
                        'type' => 'text',
                        'label' => 'Ras / Breed',
                        'placeholder' => 'Contoh: Golden Retriever, Persian',
                        'is_required' => false,
                        'sort_order' => 3,
                    ],
                    [
                        'field_key' => 'has_special_handling',
                        'type' => 'radio',
                        'label' => 'Apakah Memerlukan Penanganan Khusus (Agresif/Trauma)?',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Tidak, Hewan Jinak', 'value' => 'no'],
                            ['label' => 'Ya, Butuh Penanganan Ekstra', 'value' => 'yes'],
                        ],
                        'sort_order' => 4,
                    ],
                    [
                        'field_key' => 'handling_notes',
                        'type' => 'textarea',
                        'label' => 'Instruksi Penanganan Khusus',
                        'placeholder' => 'Jelaskan pemicu stres atau tips menenangkan hewan...',
                        'is_required' => true,
                        'visibility_conditions' => [
                            'field' => 'has_special_handling',
                            'operator' => 'eq',
                            'value' => 'yes',
                        ],
                        'sort_order' => 5,
                    ],
                ],
                'bengkel' => [
                    [
                        'field_key' => 'plate_number',
                        'type' => 'text',
                        'label' => 'Nomor Polisi (Plat Kendaraan)',
                        'placeholder' => 'Contoh: B 1234 ABC',
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'vehicle_brand_model',
                        'type' => 'text',
                        'label' => 'Merk & Model Kendaraan',
                        'placeholder' => 'Contoh: Honda HR-V 2022',
                        'is_required' => true,
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'current_km',
                        'type' => 'number',
                        'label' => 'Odometer / Kilometer Saat Ini',
                        'placeholder' => 'Contoh: 45000',
                        'is_required' => false,
                        'sort_order' => 3,
                    ],
                    [
                        'field_key' => 'complaint',
                        'type' => 'textarea',
                        'label' => 'Keluhan atau Gejala Kendaraan',
                        'placeholder' => 'Jelaskan bunyi aneh, getaran, atau bagian yang perlu dicek...',
                        'is_required' => true,
                        'sort_order' => 4,
                    ],
                ],
                'sports' => [
                    [
                        'field_key' => 'team_name',
                        'type' => 'text',
                        'label' => 'Nama Tim / Komunitas',
                        'placeholder' => 'Contoh: FC Garuda Muda',
                        'is_required' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'number_of_players',
                        'type' => 'number',
                        'label' => 'Estimasi Jumlah Pemain',
                        'placeholder' => 'Contoh: 10',
                        'is_required' => false,
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'need_referee',
                        'type' => 'radio',
                        'label' => 'Perlu Wasit / Referee Pertandingan?',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Tidak Perlu', 'value' => 'no'],
                            ['label' => 'Ya, Sediakan Wasit', 'value' => 'yes'],
                        ],
                        'sort_order' => 3,
                    ],
                    [
                        'field_key' => 'equipment_rental',
                        'type' => 'checkbox',
                        'label' => 'Sewa Rompi & Bola Pertandingan',
                        'is_required' => false,
                        'sort_order' => 4,
                    ],
                ],
                'salon' => [
                    [
                        'field_key' => 'service_focus',
                        'type' => 'select',
                        'label' => 'Fokus Perawatan',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Hair Styling / Cut', 'value' => 'hair'],
                            ['label' => 'Facial & Skin Treatment', 'value' => 'skin'],
                            ['label' => 'Nail Art & Manicure', 'value' => 'nail'],
                            ['label' => 'Lainnya', 'value' => 'other'],
                        ],
                        'sort_order' => 1,
                    ],
                    [
                        'field_key' => 'reference_photo',
                        'type' => 'file',
                        'label' => 'Foto Referensi Model / Gaya yang Diinginkan',
                        'help_text' => 'Unggah foto model atau contoh hasil potongan (JPG/PNG)',
                        'is_required' => false,
                        'sort_order' => 2,
                    ],
                    [
                        'field_key' => 'hair_notes',
                        'type' => 'textarea',
                        'label' => 'Preferensi Khusus / Pantangan Kulit',
                        'placeholder' => 'Contoh: Kulit sensitif terhadap bleach, dsb.',
                        'is_required' => false,
                        'sort_order' => 3,
                    ],
                ],
                default => [
                    [
                        'field_key' => 'notes',
                        'type' => 'textarea',
                        'label' => 'Catatan Tambahan',
                        'placeholder' => 'Ada permintaan khusus untuk pesanan Anda?',
                        'is_required' => false,
                        'sort_order' => 1,
                    ],
                ],
            };

            foreach ($fields as $f) {
                BookingFormField::create(array_merge($f, [
                    'tenant_id' => $tenant->id,
                    'form_id' => $form->id,
                    'is_active' => true,
                ]));
            }

            return $form->load('fields');
        });
    }

    /**
     * Get list of business presets available for templates (PRD 179).
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function getAvailablePresets(): array
    {
        return [
            [
                'key' => 'rental',
                'label' => 'Rental Kendaraan & Properti',
                'description' => 'Metode pengambilan (garasi/hotel), kamar hotel bersyarat, dan unggah KTP/SIM.',
            ],
            [
                'key' => 'klinik',
                'label' => 'Klinik & Layanan Kesehatan',
                'description' => 'Keluhan pasien, screening riwayat alergi obat, dan detail alergi bersyarat.',
            ],
            [
                'key' => 'pet',
                'label' => 'Pet Grooming & Penitipan Hewan',
                'description' => 'Nama hewan, jenis ras, dan instruksi penanganan khusus bila agresif.',
            ],
            [
                'key' => 'bengkel',
                'label' => 'Bengkel & Servis Kendaraan',
                'description' => 'Nomor plat polisi, merk/model, odometer kilometer, dan keluhan mesin.',
            ],
            [
                'key' => 'sports',
                'label' => 'Olahraga & Sewa Fasilitas',
                'description' => 'Nama tim, jumlah pemain, opsi wasit, dan sewa rompi/peralatan.',
            ],
            [
                'key' => 'salon',
                'label' => 'Salon & Beauty Bar',
                'description' => 'Fokus perawatan, unggah foto referensi gaya, dan preferensi kulit/rambut.',
            ],
            [
                'key' => 'custom',
                'label' => 'Formulir Standar',
                'description' => 'Formulir umum yang fleksibel untuk berbagai jasa dan layanan.',
            ],
        ];
    }
}
