<?php

namespace Tests\Feature\Workflow;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowVersion;
use App\Domain\Workflow\Services\WorkflowService;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('it auto-seeds standard default workflow for tenant if none exists (PRD 21)', function () {
    $env = $this->createTenantEnvironment('Salon Cantik');
    $tenant = $env['tenant'];
    $owner = $env['user'];

    $response = $this->actingAs($owner)->get(route('owner.workflows.index'));
    $response->assertOk();

    $this->assertDatabaseHas('workflows', [
        'tenant_id' => $tenant->id,
        'name' => 'Workflow Standar Reservasi',
        'is_default' => true,
    ]);

    $workflow = Workflow::where('tenant_id', $tenant->id)->first();
    expect($workflow)->not->toBeNull();
    expect($workflow->current_version_id)->not->toBeNull();

    $version = WorkflowVersion::find($workflow->current_version_id);
    expect($version)->not->toBeNull();
    expect($version->status)->toBe('PUBLISHED');
    expect($version->graph['nodes'])->not->toBeEmpty();
});

test('owner can access visual workflow builder canvas', function () {
    $env = $this->createTenantEnvironment('Klinik Sehat');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    $response = $this->actingAs($owner)->get(route('owner.workflows.builder', $workflow->id));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Owner/Workflows/Builder')
        ->has('workflow')
        ->has('statuses')
        ->has('presets')
    );
});

test('owner can create a new workflow assigned to a specific service', function () {
    $env = $this->createTenantEnvironment('Studio Foto');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Foto Wisuda Exclusive',
    ]);

    $response = $this->actingAs($owner)->post(route('owner.workflows.store'), [
        'name' => 'Alur Foto Wisuda',
        'description' => 'Notifikasi dan konfirmasi booking foto wisuda',
        'service_id' => $service->id,
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('workflows', [
        'tenant_id' => $tenant->id,
        'name' => 'Alur Foto Wisuda',
        'service_id' => $service->id,
    ]);

    $workflow = Workflow::where('tenant_id', $tenant->id)->where('name', 'Alur Foto Wisuda')->first();
    expect($workflow)->not->toBeNull();
    // Verify an initial draft version was generated
    expect($workflow->versions()->count())->toBe(1);
    expect($workflow->versions()->first()->status)->toBe('DRAFT');
});

test('owner can update workflow details and toggle default status', function () {
    $env = $this->createTenantEnvironment('Barbershop');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    $response = $this->actingAs($owner)->put(route('owner.workflows.update', $workflow->id), [
        'name' => 'Alur Pangkas Rambut Diperbarui',
        'description' => 'Deskripsi baru',
        'is_active' => false,
        'is_default' => true,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('workflows', [
        'id' => $workflow->id,
        'name' => 'Alur Pangkas Rambut Diperbarui',
        'is_active' => false,
    ]);
});

test('owner can delete non-default workflow', function () {
    $env = $this->createTenantEnvironment('Rental Mobil');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $customWorkflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workflow Sementara',
        'is_active' => true,
        'is_default' => false,
    ]);

    $response = $this->actingAs($owner)->delete(route('owner.workflows.destroy', $customWorkflow->id));
    $response->assertRedirect();

    $this->assertDatabaseMissing('workflows', [
        'id' => $customWorkflow->id,
    ]);
});

test('owner cannot delete default workflow if it is the only one', function () {
    $env = $this->createTenantEnvironment('Gym Fitness');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    $response = $this->actingAs($owner)->delete(route('owner.workflows.destroy', $workflow->id));
    $response->assertSessionHasErrors(['error']);

    $this->assertDatabaseHas('workflows', [
        'id' => $workflow->id,
    ]);
});

test('graph validation requires trigger node (PRD 204.5)', function () {
    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);

    $invalidGraph = [
        'nodes' => [
            ['id' => 'action_1', 'type' => 'ACTION', 'data' => ['type' => 'send_notification']],
        ],
        'edges' => [],
    ];

    $validation = $service->validateGraph($invalidGraph);
    expect($validation['valid'])->toBeFalse();
    expect($validation['errors'])->toContain('Alur kerja wajib memiliki setidaknya satu node Pemicu (Trigger).');
});

test('graph validation detects orphan non-trigger nodes (PRD 204.5)', function () {
    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);

    $graphWithOrphan = [
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'TRIGGER', 'data' => ['type' => 'booking_created']],
            ['id' => 'action_1', 'type' => 'ACTION', 'data' => ['type' => 'send_notification']],
            ['id' => 'orphan_node', 'type' => 'ACTION', 'data' => ['type' => 'change_status']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'action_1'],
        ],
    ];

    $validation = $service->validateGraph($graphWithOrphan);
    expect($validation['valid'])->toBeFalse();
    $hasOrphanError = collect($validation['errors'])->some(fn ($err) => str_contains($err, 'yatim') || str_contains($err, 'tidak terhubung'));
    expect($hasOrphanError)->toBeTrue();
});

test('graph validation detects infinite cycle loops (Kahn topological sort)', function () {
    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);

    $cyclicGraph = [
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'TRIGGER', 'data' => ['type' => 'booking_created']],
            ['id' => 'action_a', 'type' => 'ACTION', 'data' => ['type' => 'send_notification']],
            ['id' => 'action_b', 'type' => 'ACTION', 'data' => ['type' => 'change_status']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'action_a'],
            ['id' => 'e2', 'source' => 'action_a', 'target' => 'action_b'],
            ['id' => 'e3', 'source' => 'action_b', 'target' => 'action_a'], // Circular loop!
        ],
    ];

    $validation = $service->validateGraph($cyclicGraph);
    expect($validation['valid'])->toBeFalse();
    $hasCycleError = collect($validation['errors'])->some(fn ($err) => str_contains($err, 'loop'));
    expect($hasCycleError)->toBeTrue();
});

test('owner can save draft graph via API', function () {
    $env = $this->createTenantEnvironment('Dental Clinic');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    $sampleGraph = [
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'TRIGGER', 'data' => ['type' => 'booking_created']],
            ['id' => 'delay_1', 'type' => 'DELAY', 'data' => ['unit' => 'hours', 'amount' => 2]],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'delay_1'],
        ],
    ];

    $response = $this->actingAs($owner)->postJson(
        route('owner.workflows.save-draft', $workflow->id),
        ['graph' => $sampleGraph]
    );

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $draft = WorkflowVersion::where('workflow_id', $workflow->id)
        ->where('status', 'DRAFT')
        ->first();

    expect($draft)->not->toBeNull();
    expect($draft->graph['nodes'])->toHaveCount(2);
});

test('owner can publish workflow version making it immutable (PRD 23)', function () {
    $env = $this->createTenantEnvironment('Spa Resort');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    // Initial version is 1
    expect($workflow->currentVersion->version_number)->toBe(1);

    // Save a valid new draft
    $validGraph = [
        'nodes' => [
            ['id' => 'trigger_1', 'type' => 'TRIGGER', 'data' => ['type' => 'booking_created']],
            ['id' => 'action_1', 'type' => 'ACTION', 'data' => ['type' => 'send_notification', 'channel' => 'WHATSAPP']],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'action_1'],
        ],
    ];

    $service->saveDraft($workflow, $validGraph, $owner->id);

    // Publish version
    $response = $this->actingAs($owner)->postJson(
        route('owner.workflows.publish', $workflow->id)
    );

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $workflow->refresh();
    expect($workflow->currentVersion->version_number)->toBe(2);
    expect($workflow->currentVersion->status)->toBe('PUBLISHED');
    expect($workflow->currentVersion->published_at)->not->toBeNull();
});

test('owner can run dry-run simulation test endpoint', function () {
    $env = $this->createTenantEnvironment('Pusat Les');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflow = $service->seedDefaultWorkflowForTenant($tenant);

    $mockPayload = [
        'id' => 999,
        'booking_code' => 'TEST-001',
        'status' => 'PENDING',
        'payment_status' => 'PAID',
        'total_amount_idr' => 250000,
        'customer' => [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
        ],
    ];

    $response = $this->actingAs($owner)->postJson(
        route('owner.workflows.test-run', $workflow->id),
        ['mock_payload' => $mockPayload]
    );

    $response->assertOk();
    $response->assertJsonStructure([
        'success',
        'execution_id',
        'steps',
        'notifications',
        'final_status',
        'completed_at',
    ]);
    expect($response->json('success'))->toBeTrue();
    expect($response->json('steps'))->not->toBeEmpty();
});

test('owner can install preset workflow template (PRD 21)', function () {
    $env = $this->createTenantEnvironment('Klinik Estetika');
    $owner = $env['user'];
    $tenant = $env['tenant'];

    $response = $this->actingAs($owner)->post(
        route('owner.workflows.presets.install', ['preset' => 'salon'])
    );

    $response->assertRedirect();

    $this->assertDatabaseHas('workflows', [
        'tenant_id' => $tenant->id,
        'name' => 'Workflow Salon & Barbershop',
    ]);

    $presetWorkflow = Workflow::where('tenant_id', $tenant->id)
        ->where('name', 'Workflow Salon & Barbershop')
        ->first();

    expect($presetWorkflow)->not->toBeNull();
    expect($presetWorkflow->current_version_id)->not->toBeNull();
    expect($presetWorkflow->currentVersion->status)->toBe('PUBLISHED');
});

test('tenant isolation prevents cross-tenant access to workflows', function () {
    $envA = $this->createTenantEnvironment('Tenant Alpha');
    $ownerA = $envA['user'];
    $tenantA = $envA['tenant'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    $ownerB = $envB['user'];
    $tenantB = $envB['tenant'];

    /** @var WorkflowService $service */
    $service = app(WorkflowService::class);
    $workflowA = $service->seedDefaultWorkflowForTenant($tenantA);

    // Owner B cannot view Owner A's builder
    $response = $this->actingAs($ownerB)->get(route('owner.workflows.builder', $workflowA->id));
    $response->assertNotFound();

    // Owner B cannot save draft on Owner A's workflow
    $responseSave = $this->actingAs($ownerB)->postJson(
        route('owner.workflows.save-draft', $workflowA->id),
        ['graph' => ['nodes' => [], 'edges' => []]]
    );
    $responseSave->assertNotFound();

    // Owner B cannot publish Owner A's workflow
    $responsePublish = $this->actingAs($ownerB)->postJson(
        route('owner.workflows.publish', $workflowA->id)
    );
    $responsePublish->assertNotFound();
});
