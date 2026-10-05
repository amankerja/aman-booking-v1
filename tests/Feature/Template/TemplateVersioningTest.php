<?php

namespace Tests\Feature\Template;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Identity\Models\User;
use App\Domain\Template\Models\SystemTemplate;
use App\Domain\Template\Models\SystemTemplateVersion;
use App\Domain\Template\Services\TemplateCatalogService;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowVersion;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('syncDefaultTemplates seeds default workflow and form templates idempotently', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);

    $service->syncDefaultTemplates();

    $workflowTemplatesCount = SystemTemplate::where('kind', 'workflow')->count();
    $formTemplatesCount = SystemTemplate::where('kind', 'form')->count();

    expect($workflowTemplatesCount)->toBeGreaterThanOrEqual(5)
        ->and($formTemplatesCount)->toBeGreaterThanOrEqual(6);

    // Verify all seeded templates have a PUBLISHED v1.0.0 version
    $templates = SystemTemplate::with('latestVersion')->get();
    foreach ($templates as $tmpl) {
        expect($tmpl->latestVersion)->not->toBeNull()
            ->and($tmpl->latestVersion->version)->toBe('1.0.0')
            ->and($tmpl->latestVersion->status)->toBe(SystemTemplateVersion::STATUS_PUBLISHED);
    }

    // Run again to confirm idempotency (no duplicate entries)
    $service->syncDefaultTemplates();
    expect(SystemTemplate::where('kind', 'workflow')->count())->toBe($workflowTemplatesCount)
        ->and(SystemTemplate::where('kind', 'form')->count())->toBe($formTemplatesCount);
});

test('tenant can install workflow and form from template with source tracking', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env = $this->createTenantEnvironment('Barbershop Pro');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $workflow = $service->installWorkflowTemplate($tenant, 'barber_salon');

    expect($workflow->source_template_id)->not->toBeNull()
        ->and($workflow->source_template_version_id)->not->toBeNull()
        ->and($workflow->versions)->toHaveCount(1)
        ->and($workflow->publishedVersion)->not->toBeNull()
        ->and($workflow->publishedVersion->status)->toBe('PUBLISHED');

    $form = $service->installFormTemplate($tenant, 'rental_form');

    expect($form->source_template_id)->not->toBeNull()
        ->and($form->source_template_version_id)->not->toBeNull()
        ->and($form->fields)->not->toBeEmpty();
});

test('published template versions are strictly immutable', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    /** @var SystemTemplateVersion $publishedVer */
    $publishedVer = SystemTemplateVersion::where('status', SystemTemplateVersion::STATUS_PUBLISHED)->firstOrFail();

    // Attempting to mutate payload of published version must throw LogicException
    expect(fn () => $publishedVer->update(['payload' => ['illegal' => true]]))
        ->toThrow(LogicException::class, 'Versi template yang sudah dipublikasikan bersifat immutable.');

    // Attempting to delete a published version must throw LogicException
    expect(fn () => $publishedVer->delete())
        ->toThrow(LogicException::class, 'Versi template yang sudah dipublikasikan tidak dapat dihapus.');
});

test('releasing new template version does not alter existing tenant workflows or forms', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env = $this->createTenantEnvironment('Barber Tenang');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    // Install v1.0.0
    $workflow = $service->installWorkflowTemplate($tenant, 'barber_salon');
    $originalWorkflowGraph = $workflow->publishedVersion->graph;

    $form = $service->installFormTemplate($tenant, 'sports_form');
    $originalFormFieldCount = $form->fields()->count();

    // Super Admin creates and publishes v1.1.0 with additional node / fields
    /** @var SystemTemplate $workflowTemplate */
    $workflowTemplate = SystemTemplate::where('key', 'barber_salon')->firstOrFail();
    $newWorkflowGraph = $originalWorkflowGraph;
    $newWorkflowGraph['nodes'][] = [
        'id' => 'extra_step_test',
        'type' => 'action',
        'position' => ['x' => 500, 'y' => 500],
        'data' => ['label' => 'New Step in v1.1.0'],
    ];

    $v110 = SystemTemplateVersion::create([
        'system_template_id' => $workflowTemplate->id,
        'version' => '1.1.0',
        'status' => SystemTemplateVersion::STATUS_DRAFT,
        'changelog' => 'Added extra step in v1.1.0',
        'payload' => $newWorkflowGraph,
    ]);
    $service->publishTemplateVersion($v110);

    // Reload tenant workflow and verify it is completely untouched
    $workflow->refresh();
    expect($workflow->source_template_version_id)->toBe($workflowTemplate->versions()->where('version', '1.0.0')->first()->id)
        ->and($workflow->publishedVersion->graph)->toEqual($originalWorkflowGraph)
        ->and($workflow->publishedVersion->graph['nodes'])->not->toContain(fn ($node) => ($node['id'] ?? '') === 'extra_step_test');

    // Reload tenant form and verify it is completely untouched
    $form->refresh();
    expect($form->fields()->count())->toBe($originalFormFieldCount);
});

test('checkWorkflowUpdate and checkFormUpdate detect newer published versions and return diff preview', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env = $this->createTenantEnvironment('Klinik Sehat');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $workflow = $service->installWorkflowTemplate($tenant, 'clinic_health');
    $form = $service->installFormTemplate($tenant, 'clinic_form');

    // Initially on latest version (v1.0.0) -> has_update false
    $workflowCheck = $service->checkWorkflowUpdate($workflow);
    expect($workflowCheck['has_update'])->toBeFalse();

    $formCheck = $service->checkFormUpdate($form);
    expect($formCheck['has_update'])->toBeFalse();

    // Release v1.1.0 for clinic form with new field
    /** @var SystemTemplate $formTemplate */
    $formTemplate = SystemTemplate::where('key', 'clinic_form')->firstOrFail();
    $newPayload = $formTemplate->latestVersion->payload;
    $newPayload['fields'][] = [
        'field_key' => 'emergency_contact',
        'field_label' => 'Kontak Darurat',
        'field_type' => 'tel',
        'is_required' => false,
        'sort_order' => 10,
    ];

    $v11Form = SystemTemplateVersion::create([
        'system_template_id' => $formTemplate->id,
        'version' => '1.1.0',
        'status' => SystemTemplateVersion::STATUS_DRAFT,
        'changelog' => 'Added emergency contact field',
        'payload' => $newPayload,
    ]);
    $service->publishTemplateVersion($v11Form);

    // Form check now detects update
    $formCheckUpdated = $service->checkFormUpdate($form);
    expect($formCheckUpdated['has_update'])->toBeTrue()
        ->and($formCheckUpdated['target_version'])->toBe('1.1.0')
        ->and($formCheckUpdated['new_fields'])->toHaveCount(1)
        ->and($formCheckUpdated['new_fields'][0]['field_key'])->toBe('emergency_contact');
});

test('applyWorkflowUpdate copies new template into DRAFT version safely without breaking published workflow', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env = $this->createTenantEnvironment('Rental Motor Jogja');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $workflow = $service->installWorkflowTemplate($tenant, 'rental_vehicle');
    $originalPublishedVersion = $workflow->publishedVersion;

    // Release v1.2.0
    /** @var SystemTemplate $template */
    $template = SystemTemplate::where('key', 'rental_vehicle')->firstOrFail();
    $newPayload = $template->latestVersion->payload;
    $newPayload['description'] = 'Updated workflow graph v1.2.0';

    $v12 = SystemTemplateVersion::create([
        'system_template_id' => $template->id,
        'version' => '1.2.0',
        'status' => SystemTemplateVersion::STATUS_DRAFT,
        'changelog' => 'New inspection step',
        'payload' => $newPayload,
    ]);
    $service->publishTemplateVersion($v12);

    // Apply update to tenant workflow
    $draft = $service->applyWorkflowUpdate($workflow);

    expect($draft)->toBeInstanceOf(WorkflowVersion::class)
        ->and($draft->status)->toBe('DRAFT')
        ->and($draft->graph)->toEqual($newPayload);

    // Verify existing published version is untouched
    $workflow->refresh();
    expect($workflow->publishedVersion->id)->toBe($originalPublishedVersion->id)
        ->and($workflow->publishedVersion->status)->toBe('PUBLISHED')
        ->and($workflow->source_template_version_id)->toBe($v12->id);
});

test('applyFormUpdate appends newly released fields while preserving existing tenant custom fields', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env = $this->createTenantEnvironment('Futsal Arena');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $form = $service->installFormTemplate($tenant, 'sports_form');
    $initialCount = $form->fields()->count();

    // Release new version with 2 additional fields
    /** @var SystemTemplate $template */
    $template = SystemTemplate::where('key', 'sports_form')->firstOrFail();
    $newPayload = $template->latestVersion->payload;
    $newPayload['fields'][] = [
        'field_key' => 'jersey_color',
        'field_label' => 'Warna Kostum Tim',
        'field_type' => 'text',
        'is_required' => false,
        'sort_order' => 15,
    ];
    $newPayload['fields'][] = [
        'field_key' => 'referee_needed',
        'field_label' => 'Butuh Wasit',
        'field_type' => 'boolean',
        'is_required' => false,
        'sort_order' => 16,
    ];

    $v20 = SystemTemplateVersion::create([
        'system_template_id' => $template->id,
        'version' => '2.0.0',
        'status' => SystemTemplateVersion::STATUS_DRAFT,
        'changelog' => 'Added jersey color and referee option',
        'payload' => $newPayload,
    ]);
    $service->publishTemplateVersion($v20);

    // Apply update
    $updatedForm = $service->applyFormUpdate($form);

    expect($updatedForm->fields()->count())->toBe($initialCount + 2)
        ->and($updatedForm->fields()->where('field_key', 'jersey_color')->exists())->toBeTrue()
        ->and($updatedForm->fields()->where('field_key', 'referee_needed')->exists())->toBeTrue();
});

test('super admin endpoints are protected and deny non-admin users with 403', function () {
    $env = $this->createTenantEnvironment('Toko Komputer');
    $nonAdminUser = $env['user'];

    // Non-admin attempts to access admin templates
    $this->actingAs($nonAdminUser)
        ->get('/admin/templates')
        ->assertForbidden();

    $this->actingAs($nonAdminUser)
        ->post('/admin/templates/sync-defaults')
        ->assertForbidden();
});

test('super admin can view templates, create draft version, and publish it globally', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    /** @var User $superAdmin */
    $superAdmin = User::factory()->create([
        'email' => 'superadmin@amanbooking.com',
        'is_super_admin' => true,
    ]);

    // View index
    $response = $this->actingAs($superAdmin)
        ->getJson('/admin/templates');
    $response->assertOk()
        ->assertJsonStructure(['templates']);

    /** @var SystemTemplate $template */
    $template = SystemTemplate::firstOrFail();

    // View show detail
    $showResp = $this->actingAs($superAdmin)
        ->getJson("/admin/templates/{$template->id}");
    $showResp->assertOk()
        ->assertJsonStructure(['template', 'tenantUsageCount']);

    // Create a new draft version
    $storeVerResp = $this->actingAs($superAdmin)
        ->postJson("/admin/templates/{$template->id}/versions", [
            'version' => '1.5.0',
            'changelog' => 'New feature release in 1.5.0',
            'payload' => ['sample' => 'data', 'nodes' => []],
        ]);
    $storeVerResp->assertCreated();

    /** @var SystemTemplateVersion $createdVer */
    $createdVer = SystemTemplateVersion::where('system_template_id', $template->id)
        ->where('version', '1.5.0')
        ->firstOrFail();
    expect($createdVer->status)->toBe(SystemTemplateVersion::STATUS_DRAFT);

    // Publish version
    $publishResp = $this->actingAs($superAdmin)
        ->postJson("/admin/templates/versions/{$createdVer->id}/publish");
    $publishResp->assertOk();

    $createdVer->refresh();
    $template->refresh();
    expect($createdVer->status)->toBe(SystemTemplateVersion::STATUS_PUBLISHED)
        ->and($template->latest_version_id)->toBe($createdVer->id);
});

test('owner check-update and apply-update endpoints function properly with tenant isolation', function () {
    /** @var TemplateCatalogService $service */
    $service = app(TemplateCatalogService::class);
    $service->syncDefaultTemplates();

    $env1 = $this->createTenantEnvironment('Tenant Satu');
    $owner1 = $env1['user'];
    TenantContext::setTenant($env1['tenant']);

    $workflow1 = $service->installWorkflowTemplate($env1['tenant'], 'barber_salon');
    $form1 = $service->installFormTemplate($env1['tenant'], 'salon_form');

    $env2 = $this->createTenantEnvironment('Tenant Dua');
    $owner2 = $env2['user'];

    // Owner 1 can check update for their workflow and form
    TenantContext::setTenant($env1['tenant']);
    $this->actingAs($owner1)
        ->getJson("/app/workflows/{$workflow1->id}/check-update")
        ->assertOk()
        ->assertJsonFragment(['has_update' => false]);

    $this->actingAs($owner1)
        ->getJson("/app/settings/forms/{$form1->id}/check-update")
        ->assertOk()
        ->assertJsonFragment(['has_update' => false]);

    // Owner 2 cannot check or apply update to Tenant 1 workflow (Tenant Isolation 404)
    TenantContext::setTenant($env2['tenant']);
    $this->actingAs($owner2)
        ->getJson("/app/workflows/{$workflow1->id}/check-update")
        ->assertNotFound();

    $this->actingAs($owner2)
        ->postJson("/app/workflows/{$workflow1->id}/apply-update")
        ->assertNotFound();
});
