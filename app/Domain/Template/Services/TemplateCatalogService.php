<?php

namespace App\Domain\Template\Services;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Template\Models\SystemTemplate;
use App\Domain\Template\Models\SystemTemplateVersion;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateCatalogService
{
    /**
     * Get all system templates with their latest published version.
     *
     * @return Collection<int, SystemTemplate>
     */
    public function getTemplates(?string $kind = null): Collection
    {
        return SystemTemplate::query()
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->with(['latestVersion', 'versions' => fn ($q) => $q->orderBy('id', 'desc')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Seed or sync default platform-wide templates (PRD 47, 113-119, 186).
     */
    public function syncDefaultTemplates(): void
    {
        $catalog = $this->getDefaultCatalog();

        DB::transaction(function () use ($catalog) {
            foreach ($catalog as $item) {
                /** @var SystemTemplate $template */
                $template = SystemTemplate::firstOrCreate(
                    [
                        'kind' => $item['kind'],
                        'key' => $item['key'],
                    ],
                    [
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'business_type' => $item['business_type'],
                        'is_active' => true,
                    ]
                );

                // Ensure initial 1.0.0 version exists and is PUBLISHED
                /** @var SystemTemplateVersion $version */
                $version = SystemTemplateVersion::firstOrCreate(
                    [
                        'system_template_id' => $template->id,
                        'version' => '1.0.0',
                    ],
                    [
                        'status' => SystemTemplateVersion::STATUS_PUBLISHED,
                        'changelog' => 'Rilis awal standar industri (v1.0.0)',
                        'payload' => $item['payload'],
                        'published_at' => Carbon::now(),
                    ]
                );

                if (! $template->latest_version_id || $template->latest_version_id !== $version->id) {
                    $template->update(['latest_version_id' => $version->id]);
                }
            }
        });
    }

    /**
     * Install a system workflow template into a tenant (PRD 47, 186).
     */
    public function installWorkflowTemplate(Tenant $tenant, string $templateKey, ?int $serviceId = null): Workflow
    {
        $this->syncDefaultTemplates();

        $resolvedKey = match ($templateKey) {
            'barber_salon_standard', 'salon', 'barbershop' => 'barber_salon',
            'vehicle_rental_standard', 'rental' => 'rental_vehicle',
            'clinic_health_standard', 'klinik', 'clinic' => 'clinic_health',
            'sports_court_standard', 'sports', 'lapangan' => 'sports_court',
            'standar', 'standard' => 'general_standard',
            default => $templateKey,
        };

        /** @var SystemTemplate|null $template */
        $template = SystemTemplate::where('kind', SystemTemplate::KIND_WORKFLOW)
            ->where('key', $resolvedKey)
            ->with('latestVersion')
            ->first();

        if (! $template || ! $template->latestVersion) {
            throw BookingException::validationFailed("Template alur kerja '{$templateKey}' tidak ditemukan.");
        }

        /** @var SystemTemplateVersion $version */
        $version = $template->latestVersion;
        $graph = $version->payload;

        return DB::transaction(function () use ($tenant, $template, $version, $graph, $serviceId) {
            // Check if tenant already has a workflow installed from this template
            $existing = Workflow::where('tenant_id', $tenant->id)
                ->where('name', $template->name)
                ->first();

            $workflowName = $existing ? "{$template->name} (Salinan)" : $template->name;

            /** @var Workflow $workflow */
            $workflow = Workflow::create([
                'tenant_id' => $tenant->id,
                'service_id' => $serviceId,
                'name' => $workflowName,
                'description' => $template->description,
                'is_active' => true,
                'is_default' => Workflow::where('tenant_id', $tenant->id)->count() === 0,
                'source_template_id' => $template->id,
                'source_template_version_id' => $version->id,
            ]);

            /** @var WorkflowVersion $wfVersion */
            $wfVersion = WorkflowVersion::create([
                'tenant_id' => $tenant->id,
                'workflow_id' => $workflow->id,
                'version_number' => 1,
                'status' => 'PUBLISHED',
                'graph' => $graph,
                'published_at' => Carbon::now(),
            ]);

            $workflow->update([
                'current_version_id' => $wfVersion->id,
            ]);

            return $workflow->load(['currentVersion', 'sourceTemplate', 'sourceTemplateVersion']);
        });
    }

    /**
     * Install a system form template into a tenant (PRD 179, 186).
     */
    public function installFormTemplate(Tenant $tenant, string $templateKey, ?int $serviceId = null): BookingForm
    {
        $this->syncDefaultTemplates();

        $resolvedKey = match ($templateKey) {
            'rental', 'rental_property' => 'rental_form',
            'klinik', 'clinic' => 'clinic_form',
            'sports', 'sports_court' => 'sports_form',
            'salon', 'barber' => 'salon_form',
            'pet' => 'pet_form',
            'bengkel' => 'auto_form',
            'custom' => 'custom_form',
            default => $templateKey,
        };

        /** @var SystemTemplate|null $template */
        $template = SystemTemplate::where('kind', SystemTemplate::KIND_FORM)
            ->where('key', $resolvedKey)
            ->with('latestVersion')
            ->first();

        if (! $template || ! $template->latestVersion) {
            throw BookingException::validationFailed("Template formulir '{$templateKey}' tidak ditemukan.");
        }

        /** @var SystemTemplateVersion $version */
        $version = $template->latestVersion;
        /** @var array<int, array<string, mixed>> $fieldsData */
        $fieldsData = $version->payload['fields'] ?? [];

        return DB::transaction(function () use ($tenant, $template, $version, $fieldsData, $serviceId) {
            $isDefault = BookingForm::where('tenant_id', $tenant->id)->count() === 0;

            /** @var BookingForm $form */
            $form = BookingForm::create([
                'tenant_id' => $tenant->id,
                'service_id' => $serviceId,
                'name' => $template->name,
                'slug' => Str::slug($template->name).'-'.Str::random(5),
                'description' => $template->description,
                'is_default' => $isDefault,
                'is_active' => true,
                'source_template_id' => $template->id,
                'source_template_version_id' => $version->id,
            ]);

            foreach ($fieldsData as $field) {
                $type = $field['type'] ?? $field['field_type'] ?? 'text';
                $label = $field['label'] ?? $field['field_label'] ?? $field['field_key'];
                unset($field['field_type'], $field['field_label']);
                $field['type'] = $type;
                $field['label'] = $label;

                BookingFormField::create(array_merge($field, [
                    'tenant_id' => $tenant->id,
                    'form_id' => $form->id,
                    'is_active' => true,
                ]));
            }

            return $form->load(['fields', 'sourceTemplate', 'sourceTemplateVersion']);
        });
    }

    /**
     * Check if a workflow has an available template update (PRD 186).
     *
     * @return array{
     *     has_update: bool,
     *     current_version: string|null,
     *     latest_version: string|null,
     *     changelog: string|null,
     *     template_name: string|null
     * }
     */
    public function checkWorkflowUpdate(Workflow $workflow): array
    {
        if (! $workflow->source_template_id) {
            return [
                'has_update' => false,
                'current_version' => null,
                'latest_version' => null,
                'changelog' => null,
                'template_name' => null,
            ];
        }

        /** @var SystemTemplate|null $template */
        $template = SystemTemplate::with(['latestVersion'])->find($workflow->source_template_id);
        if (! $template || ! $template->latestVersion) {
            return [
                'has_update' => false,
                'current_version' => null,
                'latest_version' => null,
                'changelog' => null,
                'template_name' => null,
            ];
        }

        $currentVersionModel = $workflow->source_template_version_id
            ? SystemTemplateVersion::find($workflow->source_template_version_id)
            : null;

        $hasUpdate = $template->latest_version_id !== $workflow->source_template_version_id;

        return [
            'has_update' => $hasUpdate,
            'current_version' => $currentVersionModel instanceof SystemTemplateVersion ? $currentVersionModel->version : '1.0.0',
            'latest_version' => $template->latestVersion->version,
            'target_version' => $template->latestVersion->version,
            'changelog' => $template->latestVersion->changelog,
            'template_name' => $template->name,
        ];
    }

    /**
     * Check if a booking form has an available template update (PRD 186).
     *
     * @return array{
     *     has_update: bool,
     *     current_version: string|null,
     *     latest_version: string|null,
     *     target_version: string|null,
     *     changelog: string|null,
     *     template_name: string|null,
     *     new_fields: array<int, array<string, mixed>>
     * }
     */
    public function checkFormUpdate(BookingForm $form): array
    {
        if (! $form->source_template_id) {
            return [
                'has_update' => false,
                'current_version' => null,
                'latest_version' => null,
                'target_version' => null,
                'changelog' => null,
                'template_name' => null,
                'new_fields' => [],
            ];
        }

        /** @var SystemTemplate|null $template */
        $template = SystemTemplate::with(['latestVersion'])->find($form->source_template_id);
        if (! $template || ! $template->latestVersion) {
            return [
                'has_update' => false,
                'current_version' => null,
                'latest_version' => null,
                'target_version' => null,
                'changelog' => null,
                'template_name' => null,
                'new_fields' => [],
            ];
        }

        $currentVersionModel = $form->source_template_version_id
            ? SystemTemplateVersion::find($form->source_template_version_id)
            : null;

        $hasUpdate = $template->latest_version_id !== $form->source_template_version_id;

        $existingKeys = $form->fields()->pluck('field_key')->all();
        /** @var array<int, array<string, mixed>> $templateFields */
        $templateFields = $template->latestVersion->payload['fields'] ?? [];
        $newFields = array_values(array_filter($templateFields, fn ($f) => ! in_array($f['field_key'] ?? '', $existingKeys, true)));

        return [
            'has_update' => $hasUpdate,
            'current_version' => $currentVersionModel instanceof SystemTemplateVersion ? $currentVersionModel->version : '1.0.0',
            'latest_version' => $template->latestVersion->version,
            'target_version' => $template->latestVersion->version,
            'changelog' => $template->latestVersion->changelog,
            'template_name' => $template->name,
            'new_fields' => $newFields,
        ];
    }

    /**
     * Apply template update to tenant workflow safely as a DRAFT (PRD 186).
     * Existing published workflow remains active until owner publishes the draft.
     */
    public function applyWorkflowUpdate(Workflow $workflow, ?int $targetVersionId = null): WorkflowVersion
    {
        if (! $workflow->source_template_id) {
            throw BookingException::validationFailed('Alur kerja ini tidak tertaut pada template global.');
        }

        /** @var SystemTemplate $template */
        $template = SystemTemplate::findOrFail($workflow->source_template_id);

        /** @var SystemTemplateVersion|null $targetVersion */
        $targetVersion = $targetVersionId
            ? SystemTemplateVersion::where('system_template_id', $template->id)->findOrFail($targetVersionId)
            : $template->latestVersion;

        if (! $targetVersion || $targetVersion->status !== SystemTemplateVersion::STATUS_PUBLISHED) {
            throw BookingException::validationFailed('Versi template tujuan belum dipublikasikan.');
        }

        return DB::transaction(function () use ($workflow, $targetVersion) {
            $graph = $targetVersion->payload;

            // Compute next version number for tenant draft
            $maxVersionNumber = (int) $workflow->versions()->max('version_number');

            /** @var WorkflowVersion|null $existingDraft */
            $existingDraft = $workflow->draftVersion;
            if ($existingDraft) {
                $existingDraft->update([
                    'graph' => $graph,
                ]);
                $draft = $existingDraft;
            } else {
                /** @var WorkflowVersion $draft */
                $draft = WorkflowVersion::create([
                    'tenant_id' => $workflow->tenant_id,
                    'workflow_id' => $workflow->id,
                    'version_number' => $maxVersionNumber + 1,
                    'status' => 'DRAFT',
                    'graph' => $graph,
                ]);
            }

            // Update source template version pointer
            $workflow->update([
                'source_template_version_id' => $targetVersion->id,
            ]);

            return $draft;
        });
    }

    /**
     * Apply template update to tenant form by safely adding new non-existing fields (PRD 186).
     */
    public function applyFormUpdate(BookingForm $form, ?int $targetVersionId = null): BookingForm
    {
        if (! $form->source_template_id) {
            throw BookingException::validationFailed('Formulir ini tidak tertaut pada template global.');
        }

        /** @var SystemTemplate $template */
        $template = SystemTemplate::findOrFail($form->source_template_id);

        /** @var SystemTemplateVersion|null $targetVersion */
        $targetVersion = $targetVersionId
            ? SystemTemplateVersion::where('system_template_id', $template->id)->findOrFail($targetVersionId)
            : $template->latestVersion;

        if (! $targetVersion || $targetVersion->status !== SystemTemplateVersion::STATUS_PUBLISHED) {
            throw BookingException::validationFailed('Versi template tujuan belum dipublikasikan.');
        }

        return DB::transaction(function () use ($form, $targetVersion) {
            /** @var array<int, array<string, mixed>> $newFields */
            $newFields = $targetVersion->payload['fields'] ?? [];
            $existingKeys = $form->fields()->pluck('field_key')->all();

            $maxSort = (int) $form->fields()->max('sort_order');

            foreach ($newFields as $f) {
                if (! in_array($f['field_key'] ?? '', $existingKeys, true)) {
                    $maxSort++;
                    $type = $f['type'] ?? $f['field_type'] ?? 'text';
                    $label = $f['label'] ?? $f['field_label'] ?? $f['field_key'];
                    unset($f['field_type'], $f['field_label']);
                    $f['type'] = $type;
                    $f['label'] = $label;

                    BookingFormField::create(array_merge($f, [
                        'tenant_id' => $form->tenant_id,
                        'form_id' => $form->id,
                        'sort_order' => $maxSort,
                        'is_active' => true,
                    ]));
                }
            }

            $form->update([
                'source_template_version_id' => $targetVersion->id,
            ]);

            return $form->load(['fields']);
        });
    }

    /**
     * Publish a new version of a system template (Super Admin, PRD 185).
     */
    public function publishTemplateVersion(SystemTemplateVersion $version): SystemTemplateVersion
    {
        return DB::transaction(function () use ($version) {
            $version->update([
                'status' => SystemTemplateVersion::STATUS_PUBLISHED,
                'published_at' => Carbon::now(),
            ]);

            $version->template->update([
                'latest_version_id' => $version->id,
            ]);

            return $version->fresh();
        });
    }

    /**
     * Default pre-packaged industry templates catalog (PRD 47, 113-119).
     *
     * @return array<int, array{
     *     kind: string,
     *     key: string,
     *     name: string,
     *     description: string,
     *     business_type: string,
     *     payload: array<string, mixed>
     * }>
     */
    protected function getDefaultCatalog(): array
    {
        return [
            // 1. Workflow: Barber & Salon (PRD 47.1, 113.1)
            [
                'kind' => SystemTemplate::KIND_WORKFLOW,
                'key' => 'barber_salon',
                'name' => 'Alur Reservasi Barbershop & Salon',
                'description' => 'Konfirmasi instan, penugasan capster/terapis, jeda pengingat H-1 hari via WhatsApp.',
                'business_type' => 'Barbershop / Salon',
                'payload' => [
                    'nodes' => [
                        [
                            'id' => 'trigger_start',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Booking Dibuat',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'act_confirm',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Konfirmasi Otomatis',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                        [
                            'id' => 'delay_h1',
                            'type' => 'delay',
                            'position' => ['x' => 250, 'y' => 310],
                            'data' => [
                                'label' => 'Jeda Pengingat (24 Jam)',
                                'category' => 'delay',
                                'type' => 'delay',
                                'config' => ['duration' => 24, 'unit' => 'hours'],
                            ],
                        ],
                        [
                            'id' => 'act_wa_reminder',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 440],
                            'data' => [
                                'label' => 'Kirim WA Reminder',
                                'category' => 'action',
                                'type' => 'send_notification',
                                'config' => ['channel' => 'whatsapp', 'template' => 'reminder_h1'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'trigger_start', 'target' => 'act_confirm'],
                        ['id' => 'e2', 'source' => 'act_confirm', 'target' => 'delay_h1'],
                        ['id' => 'e3', 'source' => 'delay_h1', 'target' => 'act_wa_reminder'],
                    ],
                ],
            ],

            // 2. Workflow: Rental Kendaraan & Properti (PRD 47.2)
            [
                'kind' => SystemTemplate::KIND_WORKFLOW,
                'key' => 'rental_vehicle',
                'name' => 'Alur Rental Mobil / Motor',
                'description' => 'Verifikasi dokumen penyewa, instruksi deposit DP, dan panduan serah terima unit kendaraan.',
                'business_type' => 'Rental Kendaraan',
                'payload' => [
                    'nodes' => [
                        [
                            'id' => 'trigger_start',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Permintaan Rental Masuk',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'cond_payment',
                            'type' => 'condition',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Cek Status DP',
                                'category' => 'condition',
                                'type' => 'payment_status',
                                'config' => ['payment_status' => 'PAID'],
                            ],
                        ],
                        [
                            'id' => 'act_confirm_unit',
                            'type' => 'action',
                            'position' => ['x' => 100, 'y' => 320],
                            'data' => [
                                'label' => 'Konfirmasi Alokasi Unit',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                        [
                            'id' => 'act_pending_doc',
                            'type' => 'action',
                            'position' => ['x' => 400, 'y' => 320],
                            'data' => [
                                'label' => 'Tunggu Pembayaran & KTP',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'PENDING'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'trigger_start', 'target' => 'cond_payment'],
                        ['id' => 'e2', 'source' => 'cond_payment', 'target' => 'act_confirm_unit', 'sourceHandle' => 'yes'],
                        ['id' => 'e3', 'source' => 'cond_payment', 'target' => 'act_pending_doc', 'sourceHandle' => 'no'],
                    ],
                ],
            ],

            // 3. Workflow: Klinik & Kesehatan (PRD 47.5, 113.7)
            [
                'kind' => SystemTemplate::KIND_WORKFLOW,
                'key' => 'clinic_health',
                'name' => 'Alur Janji Temu Dokter / Klinik',
                'description' => 'Triase awal, verifikasi riwayat medis/alergi, dan konfirmasi reservasi dokter.',
                'business_type' => 'Klinik / Dokter',
                'payload' => [
                    'nodes' => [
                        [
                            'id' => 'trigger_start',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Pendaftaran Pasien Masuk',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'act_confirm_patient',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Konfirmasi Antrean',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                        [
                            'id' => 'act_wa_prep',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 310],
                            'data' => [
                                'label' => 'Kirim WA Panduan Pasien',
                                'category' => 'action',
                                'type' => 'send_notification',
                                'config' => ['channel' => 'whatsapp', 'template' => 'clinic_prep_instruction'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'trigger_start', 'target' => 'act_confirm_patient'],
                        ['id' => 'e2', 'source' => 'act_confirm_patient', 'target' => 'act_wa_prep'],
                    ],
                ],
            ],

            // 4. Form: Rental Kendaraan (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'rental_form',
                'name' => 'Formulir Rental Mobil & Motor',
                'description' => 'Metode pengambilan (garasi/hotel), nomor kamar kondisional, dan unggah berkas KTP/SIM.',
                'business_type' => 'Rental Kendaraan',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'pickup_mode',
                            'type' => 'radio',
                            'label' => 'Metode Pengambilan',
                            'is_required' => true,
                            'options' => [
                                ['label' => 'Ambil di Garasi / Kantor', 'value' => 'office'],
                                ['label' => 'Antar ke Hotel / Penginapan', 'value' => 'hotel'],
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
                            'field_key' => 'id_card_file',
                            'type' => 'file',
                            'label' => 'Foto KTP / SIM Pengemudi',
                            'help_text' => 'Format JPG, PNG atau PDF (Maks. 5 MB)',
                            'is_required' => false,
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],

            // 5. Form: Pasien Klinik (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'clinic_form',
                'name' => 'Formulir Pasien Klinik',
                'description' => 'Keluhan utama pasien, skrining alergi obat, dan detail riwayat pengobatan.',
                'business_type' => 'Klinik / Dokter',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'complaint',
                            'type' => 'textarea',
                            'label' => 'Keluhan Utama',
                            'placeholder' => 'Jelaskan keluhan atau gejala yang dirasakan...',
                            'is_required' => true,
                            'sort_order' => 1,
                        ],
                        [
                            'field_key' => 'allergy_check',
                            'type' => 'radio',
                            'label' => 'Apakah Memiliki Riwayat Alergi Obat/Bahan?',
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
                            'label' => 'Sebutkan Jenis Alergi',
                            'placeholder' => 'Contoh: Alergi Penisilin / Antibiotik',
                            'is_required' => true,
                            'visibility_conditions' => [
                                'field' => 'allergy_check',
                                'operator' => 'eq',
                                'value' => 'yes',
                            ],
                            'sort_order' => 3,
                        ],
                    ],
                ],
            ],

            // 6. Workflow: Lapangan & Olahraga (PRD 47.4, 113.6)
            [
                'kind' => SystemTemplate::KIND_WORKFLOW,
                'key' => 'sports_court',
                'name' => 'Alur Sewa Lapangan Olahraga',
                'description' => 'Konfirmasi instan sewa lapangan futsal/badminton, pengingat jadwal, dan auto-selesai.',
                'business_type' => 'Olahraga / Lapangan',
                'payload' => [
                    'nodes' => [
                        [
                            'id' => 'trigger_start',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Sewa Lapangan Dipesan',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'act_confirm',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Konfirmasi Lapangan',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'trigger_start', 'target' => 'act_confirm'],
                    ],
                ],
            ],

            // 7. Workflow: Standar Reservasi Umum
            [
                'kind' => SystemTemplate::KIND_WORKFLOW,
                'key' => 'general_standard',
                'name' => 'Alur Reservasi Standar Bisnis',
                'description' => 'Alur umum booking: validasi pembayaran DP/lunas, konfirmasi otomatis, dan kirim pengingat.',
                'business_type' => 'Umum / Serbaguna',
                'payload' => [
                    'nodes' => [
                        [
                            'id' => 'trigger_start',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Booking Masuk',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'act_confirm',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Konfirmasi Booking',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'trigger_start', 'target' => 'act_confirm'],
                    ],
                ],
            ],

            // 8. Form: Sewa Lapangan Olahraga (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'sports_form',
                'name' => 'Formulir Sewa Lapangan Olahraga',
                'description' => 'Nama tim, jenis olahraga, dan kebutuhan perlengkapan tambahan seperti rompi/bola.',
                'business_type' => 'Olahraga / Lapangan',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'team_name',
                            'type' => 'text',
                            'label' => 'Nama Tim / Komunitas',
                            'placeholder' => 'Contoh: FC Garuda Muda',
                            'is_required' => true,
                            'sort_order' => 1,
                        ],
                        [
                            'field_key' => 'need_referee',
                            'type' => 'checkbox',
                            'label' => 'Sewa Rompi & Bola Pertandingan',
                            'is_required' => false,
                            'sort_order' => 2,
                        ],
                    ],
                ],
            ],

            // 9. Form: Salon & Barbershop (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'salon_form',
                'name' => 'Formulir Salon & Barbershop',
                'description' => 'Preferensi capster/stylist, model rambut atau riwayat perawatan kimia rambut.',
                'business_type' => 'Barbershop / Salon',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'hair_notes',
                            'type' => 'textarea',
                            'label' => 'Catatan Model / Perawatan Rambut',
                            'placeholder' => 'Contoh: Fade cut rendah, cat warna ash grey...',
                            'is_required' => false,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],

            // 10. Form: Pet Grooming & Care (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'pet_form',
                'name' => 'Formulir Pet Care & Grooming',
                'description' => 'Nama anabul, jenis/ras hewan, dan riwayat sifat khusus/vaksinasi.',
                'business_type' => 'Pet Grooming & Hotel',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'pet_name',
                            'type' => 'text',
                            'label' => 'Nama Hewan',
                            'placeholder' => 'Contoh: Chiko',
                            'is_required' => true,
                            'sort_order' => 1,
                        ],
                        [
                            'field_key' => 'pet_type',
                            'type' => 'select',
                            'label' => 'Jenis Hewan',
                            'is_required' => true,
                            'options' => [
                                ['label' => 'Kucing', 'value' => 'cat'],
                                ['label' => 'Anjing', 'value' => 'dog'],
                                ['label' => 'Lainnya', 'value' => 'other'],
                            ],
                            'sort_order' => 2,
                        ],
                    ],
                ],
            ],

            // 11. Form: Bengkel & Service Kendaraan (PRD 179)
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'auto_form',
                'name' => 'Formulir Servis & Bengkel Otomotif',
                'description' => 'Nomor polisi kendaraan, merek/tipe kendaraan, dan keluhan kendala teknis.',
                'business_type' => 'Bengkel Otomotif',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'license_plate',
                            'type' => 'text',
                            'label' => 'Nomor Polisi (Plat Nomor)',
                            'placeholder' => 'Contoh: B 1234 ABC',
                            'is_required' => true,
                            'sort_order' => 1,
                        ],
                        [
                            'field_key' => 'vehicle_complaint',
                            'type' => 'textarea',
                            'label' => 'Keluhan / Masalah Kendaraan',
                            'placeholder' => 'Suara rem berdecit, ganti oli mesin...',
                            'is_required' => true,
                            'sort_order' => 2,
                        ],
                    ],
                ],
            ],

            // 12. Form: Custom Standard
            [
                'kind' => SystemTemplate::KIND_FORM,
                'key' => 'custom_form',
                'name' => 'Formulir Reservasi Umum',
                'description' => 'Formulir umum untuk reservasi layanan fleksibel dengan catatan kebutuhan tambahan.',
                'business_type' => 'Umum / Fleksibel',
                'payload' => [
                    'fields' => [
                        [
                            'field_key' => 'booking_note',
                            'type' => 'textarea',
                            'label' => 'Catatan Tambahan untuk Reservasi',
                            'placeholder' => 'Tuliskan catatan khusus atau permintaan tambahan di sini...',
                            'is_required' => false,
                            'sort_order' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }
}
