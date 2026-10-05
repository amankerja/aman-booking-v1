<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Booking\Models\BookingCustomField;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Form\Services\FormService;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingFormController extends Controller
{
    public function __construct(
        protected FormService $formService
    ) {}

    /**
     * Display list of forms, fields, and presets (PRD 26, 27, 179).
     */
    public function index(Request $request): Response|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $forms = BookingForm::where('tenant_id', $tenant->id)
            ->with([
                'service:id,name',
                'fields' => fn ($q) => $q->orderBy('sort_order', 'asc'),
            ])
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // If no forms exist yet, auto-seed standard form
        if ($forms->isEmpty()) {
            $this->formService->seedDefaultFormForTenant($tenant, 'custom');
            $forms = BookingForm::where('tenant_id', $tenant->id)
                ->with([
                    'service:id,name',
                    'fields' => fn ($q) => $q->orderBy('sort_order', 'asc'),
                ])
                ->get();
        }

        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $presets = $this->formService->getAvailablePresets();

        if ($request->wantsJson()) {
            return response()->json([
                'forms' => $forms,
                'services' => $services,
                'presets' => $presets,
            ]);
        }

        return Inertia::render('Owner/Settings/Forms', [
            'forms' => $forms,
            'services' => $services,
            'presets' => $presets,
        ]);
    }

    /**
     * Create a new booking form.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($tenant, $validated) {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault && empty($validated['service_id'])) {
                BookingForm::where('tenant_id', $tenant->id)
                    ->whereNull('service_id')
                    ->update(['is_default' => false]);
            }

            BookingForm::create([
                'tenant_id' => $tenant->id,
                'service_id' => $validated['service_id'] ?? null,
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']).'-'.Str::random(5),
                'description' => $validated['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });

        return back()->with('success', 'Formulir pemesanan berhasil dibuat.');
    }

    /**
     * Update an existing booking form.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $form = BookingForm::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($tenant, $form, $validated) {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault && empty($validated['service_id'])) {
                BookingForm::where('tenant_id', $tenant->id)
                    ->where('id', '!=', $form->id)
                    ->whereNull('service_id')
                    ->update(['is_default' => false]);
            }

            $form->update([
                'service_id' => $validated['service_id'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);
        });

        return back()->with('success', 'Formulir berhasil diperbarui.');
    }

    /**
     * Delete a booking form.
     */
    public function destroy(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $form = BookingForm::where('tenant_id', $tenant->id)->findOrFail($id);

        $form->delete();

        return back()->with('success', 'Formulir berhasil dihapus.');
    }

    /**
     * Add a field to a form.
     */
    public function addField(Request $request, int $formId): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $form = BookingForm::where('tenant_id', $tenant->id)->findOrFail($formId);

        $validated = $request->validate([
            'field_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'type' => [
                'required',
                'string',
                'in:text,textarea,number,currency,phone,email,date,time,select,multiselect,radio,checkbox,file,address',
            ],
            'label' => ['required', 'string', 'max:150'],
            'placeholder' => ['nullable', 'string', 'max:150'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string'],
            'options.*.value' => ['required_with:options', 'string'],
            'validation_rules' => ['nullable', 'array'],
            'visibility_conditions' => ['nullable', 'array'],
        ]);

        $maxSort = BookingFormField::where('form_id', $form->id)->max('sort_order') ?? 0;

        BookingFormField::create([
            'tenant_id' => $tenant->id,
            'form_id' => $form->id,
            'field_key' => $validated['field_key'],
            'type' => $validated['type'],
            'label' => $validated['label'],
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'default_value' => $validated['default_value'] ?? null,
            'options' => $validated['options'] ?? null,
            'validation_rules' => $validated['validation_rules'] ?? null,
            'visibility_conditions' => $validated['visibility_conditions'] ?? null,
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Kolom baru berhasil ditambahkan.');
    }

    /**
     * Update an existing form field.
     */
    public function updateField(Request $request, int $formId, int $fieldId): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        BookingForm::where('tenant_id', $tenant->id)->findOrFail($formId);
        $field = BookingFormField::where('tenant_id', $tenant->id)
            ->where('form_id', $formId)
            ->findOrFail($fieldId);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'placeholder' => ['nullable', 'string', 'max:150'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string'],
            'options.*.value' => ['required_with:options', 'string'],
            'validation_rules' => ['nullable', 'array'],
            'visibility_conditions' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $field->update([
            'label' => $validated['label'],
            'placeholder' => $validated['placeholder'] ?? null,
            'help_text' => $validated['help_text'] ?? null,
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'default_value' => $validated['default_value'] ?? null,
            'options' => $validated['options'] ?? null,
            'validation_rules' => $validated['validation_rules'] ?? null,
            'visibility_conditions' => $validated['visibility_conditions'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', 'Kolom berhasil diperbarui.');
    }

    /**
     * Delete a field from a form.
     */
    public function deleteField(int $formId, int $fieldId): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        BookingForm::where('tenant_id', $tenant->id)->findOrFail($formId);
        $field = BookingFormField::where('tenant_id', $tenant->id)
            ->where('form_id', $formId)
            ->findOrFail($fieldId);

        $field->delete();

        return back()->with('success', 'Kolom berhasil dihapus.');
    }

    /**
     * Reorder fields in bulk.
     */
    public function reorderFields(Request $request, int $formId): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        BookingForm::where('tenant_id', $tenant->id)->findOrFail($formId);

        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer'],
            'fields.*.sort_order' => ['required', 'integer'],
        ]);

        DB::transaction(function () use ($tenant, $formId, $validated) {
            foreach ($validated['fields'] as $item) {
                BookingFormField::where('tenant_id', $tenant->id)
                    ->where('form_id', $formId)
                    ->where('id', $item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Urutan kolom berhasil diperbarui.');
    }

    /**
     * Install a business preset template (PRD 179).
     */
    public function installPreset(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'preset' => ['required', 'string', 'in:rental,klinik,pet,bengkel,sports,salon,custom'],
        ]);

        $this->formService->seedDefaultFormForTenant($tenant, $validated['preset']);

        return back()->with('success', 'Template formulir berhasil dipasang.');
    }

    /**
     * Download or view private custom field uploaded file securely.
     */
    public function downloadFile(int $id): StreamedResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var BookingCustomField $customField */
        $customField = BookingCustomField::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($customField->field_type !== 'file' || empty($customField->value_text)) {
            abort(404, 'Berkas tidak ditemukan.');
        }

        if (! Storage::disk('local')->exists($customField->value_text)) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($customField->value_text);
    }
}
