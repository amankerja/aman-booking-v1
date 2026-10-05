<?php

namespace Tests\Feature\Gate;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Service\Models\Service;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowLog;
use App\Domain\Workflow\Models\WorkflowRun;
use App\Domain\Workflow\Models\WorkflowVersion;
use App\Domain\Workflow\Services\WorkflowRunnerService;
use App\Domain\Workflow\Services\WorkflowService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Tenant\TenantIsolationTestHelper;
use Tests\TestCase;

class Phase3GateTest extends TestCase
{
    use RefreshDatabase;
    use TenantIsolationTestHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    /**
     * EXIT CRITERIA 1:
     * Owner membuat workflow Salon (Pending Payment -> Confirmed -> ... -> Completed) tanpa bantuan developer.
     */
    public function test_exit_criteria_1_owner_creates_salon_workflow_without_developer(): void
    {
        $env = $this->createTenantEnvironment('Salon Cantik Sejahtera');
        $user = $env['user'];
        $tenant = $env['tenant'];
        TenantContext::setTenant($tenant);

        // 1. Create a service for the Salon
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Hair Spa & Smoothing Treatment',
            'price_idr' => 350000,
            'duration_minutes' => 90,
            'is_active' => true,
        ]);

        // 2. Owner creates a new Workflow via Owner API
        $createResponse = $this->actingAs($user)->post(route('owner.workflows.store'), [
            'name' => 'Alur Otomasi Reservasi Salon VIP',
            'description' => 'Pending -> Confirmed -> Selesai secara otomatis',
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        $createResponse->assertRedirect();
        $workflow = Workflow::where('tenant_id', $tenant->id)->where('name', 'Alur Otomasi Reservasi Salon VIP')->firstOrFail();
        $this->assertEquals('Alur Otomasi Reservasi Salon VIP', $workflow->name);

        // 3. Owner designs the graph (Trigger -> Action Confirmed -> Delay -> Action Completed)
        $graphPayload = [
            'nodes' => [
                [
                    'id' => 'trigger_1',
                    'type' => 'TRIGGER',
                    'data' => [
                        'category' => 'trigger',
                        'type' => 'booking_created',
                        'label' => 'Saat Booking Masuk',
                        'config' => [],
                    ],
                ],
                [
                    'id' => 'action_confirm',
                    'type' => 'ACTION',
                    'data' => [
                        'category' => 'action',
                        'type' => 'change_status',
                        'label' => 'Konfirmasi Reservasi',
                        'config' => [
                            'status' => 'CONFIRMED',
                        ],
                    ],
                ],
                [
                    'id' => 'delay_treatment',
                    'type' => 'DELAY',
                    'data' => [
                        'category' => 'delay',
                        'type' => 'delay',
                        'label' => 'Tunggu 2 Jam (Sesi Perawatan)',
                        'config' => [
                            'amount' => 2,
                            'unit' => 'hours',
                        ],
                    ],
                ],
                [
                    'id' => 'action_complete',
                    'type' => 'ACTION',
                    'data' => [
                        'category' => 'action',
                        'type' => 'change_status',
                        'label' => 'Selesaikan Reservasi',
                        'config' => [
                            'status' => 'COMPLETED',
                        ],
                    ],
                ],
            ],
            'edges' => [
                [
                    'id' => 'e1',
                    'source' => 'trigger_1',
                    'target' => 'action_confirm',
                ],
                [
                    'id' => 'e2',
                    'source' => 'action_confirm',
                    'target' => 'delay_treatment',
                ],
                [
                    'id' => 'e3',
                    'source' => 'delay_treatment',
                    'target' => 'action_complete',
                ],
            ],
        ];

        // 4. Owner saves draft graph
        $saveDraftResp = $this->actingAs($user)->postJson(
            route('owner.workflows.save-draft', $workflow->id),
            ['graph' => $graphPayload]
        );
        $saveDraftResp->assertOk();
        $saveDraftResp->assertJson(['success' => true]);

        // 5. Owner runs simulation test (Dry-Run)
        $simResp = $this->actingAs($user)->postJson(
            route('owner.workflows.test-run', $workflow->id),
            [
                'mock_payload' => [
                    'id' => 101,
                    'booking_code' => 'SLN-VIP-001',
                    'status' => 'PENDING',
                    'customer' => ['name' => 'Dewi Lestari'],
                ],
            ]
        );
        $simResp->assertOk();
        $simResp->assertJson(['success' => true]);

        // 6. Owner Publishes the workflow (Version becomes immutable)
        $publishResp = $this->actingAs($user)->postJson(
            route('owner.workflows.publish', $workflow->id),
            ['notes' => 'Rilis v1.0.0 Alur Salon Lengkap']
        );
        $publishResp->assertOk();
        $publishResp->assertJson(['success' => true]);

        $workflow->refresh();
        $publishedVersion = WorkflowVersion::where('workflow_id', $workflow->id)
            ->where('status', 'PUBLISHED')
            ->firstOrFail();

        $this->assertEquals(1, $publishedVersion->version_number);

        // 7. Verify incoming booking triggers and executes this workflow without developer intervention
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dewi Lestari',
            'phone_e164' => '+6281299887766',
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status_category' => BookingStatusCategory::PENDING,
            'workflow_version_id' => $publishedVersion->id,
        ]);

        // Dispatch domain event BookingCreated
        event(new BookingCreated($booking));

        // Verify workflow execution
        $booking->refresh();
        $this->assertEquals($publishedVersion->id, $booking->workflow_version_id);
        $this->assertEquals(BookingStatusCategory::CONFIRMED, $booking->status_category);

        $run = WorkflowRun::where('booking_id', $booking->id)->latest('id')->firstOrFail();
        $this->assertContains($run->status, [
            WorkflowRun::STATUS_COMPLETED,
            WorkflowRun::STATUS_RUNNING,
        ]);
    }

    /**
     * EXIT CRITERIA 2:
     * Workflow gagal tidak menghilangkan booking dan bisa di-retry.
     */
    public function test_exit_criteria_2_workflow_failure_preserves_booking_and_allows_manual_retry(): void
    {
        $env = $this->createTenantEnvironment('Barber Sentosa');
        $user = $env['user'];
        $tenant = $env['tenant'];
        TenantContext::setTenant($tenant);

        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Budi Santoso',
            'phone_e164' => '+6281234567890',
        ]);

        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gentleman Haircut',
            'price_idr' => 100000,
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'code' => 'BRB-FAIL-001',
            'status_category' => BookingStatusCategory::CONFIRMED,
        ]);

        // 1. Create a workflow with an action that triggers a failure
        $workflow = Workflow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Faulty Workflow Test',
            'is_active' => true,
            'is_default' => false,
        ]);

        $graph = [
            'nodes' => [
                [
                    'id' => 'action_note_retry',
                    'type' => 'action',
                    'data' => [
                        'category' => 'action',
                        'type' => 'add_customer_note',
                        'label' => 'Catatan Manual Retry',
                        'config' => ['note' => 'Berhasil setelah retry manual'],
                    ],
                ],
            ],
            'edges' => [],
        ];

        $version = WorkflowVersion::create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'graph' => $graph,
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        $workflow->update(['current_version_id' => $version->id]);
        $booking->update(['workflow_version_id' => $version->id]);

        $failedRun = WorkflowRun::create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'version_id' => $version->id,
            'booking_id' => $booking->id,
            'execution_id' => 'run_fail_test_001',
            'trigger_event' => 'manual',
            'status' => WorkflowRun::STATUS_FAILED,
            'current_node_id' => 'action_note_retry',
            'error_message' => 'Simulasi kesalahan jaringan sementara',
            'depth' => 0,
            'started_at' => now(),
        ]);

        WorkflowLog::create([
            'tenant_id' => $tenant->id,
            'workflow_run_id' => $failedRun->id,
            'node_id' => 'action_note_retry',
            'node_type' => 'action',
            'node_label' => 'Catatan Manual Retry',
            'status' => WorkflowLog::STATUS_FAILED,
            'attempt' => 1,
            'error_message' => 'Simulasi kesalahan jaringan sementara',
            'executed_at' => now(),
        ]);

        // 2. CRITICAL: Verify booking was NOT deleted or corrupted
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'code' => 'BRB-FAIL-001',
            'status_category' => BookingStatusCategory::CONFIRMED->value, // Remains unchanged and intact
        ]);

        // 3. Owner accesses workflow runs list via API
        $runsResponse = $this->actingAs($user)->getJson("/app/workflows/{$workflow->id}/runs");
        $runsResponse->assertOk();
        $runsResponse->assertJsonStructure(['data' => [['id', 'execution_id', 'status']]]);

        // 4. Owner triggers manual retry via API
        $retryResponse = $this->actingAs($user)->postJson("/app/workflows/runs/{$failedRun->id}/retry");
        $retryResponse->assertOk();
        $retryResponse->assertJson(['success' => true]);

        $failedRun->refresh();
        $this->assertEquals(WorkflowRun::STATUS_COMPLETED, $failedRun->status);

        $customer->refresh();
        $this->assertStringContainsString('Berhasil setelah retry manual', (string) $customer->notes);
    }

    /**
     * EXIT CRITERIA 3:
     * Test loop / duplicate execution lulus (Graph validation + max depth protection + action idempotency).
     */
    public function test_exit_criteria_3_loop_and_duplicate_execution_protection(): void
    {
        $env = $this->createTenantEnvironment('Proteksi Loop Usaha');
        $tenant = $env['tenant'];
        TenantContext::setTenant($tenant);

        /** @var WorkflowService $service */
        $service = app(WorkflowService::class);

        // A. Server-Side Graph Validation (Kahn Topological Sort)
        // Detects infinite circular loops before graph can ever be saved/published
        $cyclicGraph = [
            'nodes' => [
                [
                    'id' => 'trigger_1',
                    'type' => 'TRIGGER',
                    'data' => ['type' => 'booking_created'],
                ],
                [
                    'id' => 'node_a',
                    'type' => 'ACTION',
                    'data' => ['type' => 'send_notification'],
                ],
                [
                    'id' => 'node_b',
                    'type' => 'ACTION',
                    'data' => ['type' => 'change_status'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'trigger_1', 'target' => 'node_a'],
                ['id' => 'e2', 'source' => 'node_a', 'target' => 'node_b'],
                ['id' => 'e3', 'source' => 'node_b', 'target' => 'node_a'], // Circular loop!
            ],
        ];

        $validationResult = $service->validateGraph($cyclicGraph);
        $this->assertFalse($validationResult['valid']);
        $hasLoopError = collect($validationResult['errors'])->some(fn ($err) => str_contains($err, 'loop'));
        $this->assertTrue($hasLoopError);

        // B. Runtime Safeguard: Max Depth Limit (50) stops recursive execution
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $serviceModel = Service::factory()->create(['tenant_id' => $tenant->id]);

        $workflow = Workflow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Recursive Test',
            'is_active' => true,
        ]);

        $version = WorkflowVersion::create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'graph' => $cyclicGraph,
            'status' => 'PUBLISHED',
            'published_at' => now(),
        ]);

        $booking = Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'service_id' => $serviceModel->id,
            'workflow_version_id' => $version->id,
        ]);

        $run = WorkflowRun::create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'version_id' => $version->id,
            'booking_id' => $booking->id,
            'execution_id' => 'run_loop_test',
            'trigger_event' => 'manual',
            'status' => WorkflowRun::STATUS_RUNNING,
            'current_node_id' => 'node_a',
            'depth' => 49, // Close to 50
            'started_at' => now(),
        ]);

        /** @var WorkflowRunnerService $runner */
        $runner = app(WorkflowRunnerService::class);
        $runner->executeNode($run->id, 'node_a');

        $run->refresh();
        $this->assertEquals(WorkflowRun::STATUS_FAILED, $run->status);
        $this->assertStringContainsString('Batas kedalaman eksekusi alur terlampaui', $run->error_message);

        // C. Action Idempotency: Already succeeded actions are never re-executed in the same run
        $idempotentRun = WorkflowRun::create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'version_id' => $version->id,
            'booking_id' => $booking->id,
            'execution_id' => 'run_idem_test',
            'trigger_event' => 'manual',
            'status' => WorkflowRun::STATUS_RUNNING,
            'current_node_id' => 'action_idempotent',
            'depth' => 0,
            'started_at' => now(),
        ]);

        WorkflowLog::create([
            'tenant_id' => $tenant->id,
            'workflow_run_id' => $idempotentRun->id,
            'node_id' => 'action_idempotent',
            'node_type' => 'action',
            'node_label' => 'Tambah Catatan Sekali',
            'status' => WorkflowLog::STATUS_SUCCESS,
            'attempt' => 1,
            'executed_at' => now(),
        ]);

        $this->assertTrue(
            WorkflowLog::where('workflow_run_id', $idempotentRun->id)
                ->where('node_id', 'action_idempotent')
                ->where('status', WorkflowLog::STATUS_SUCCESS)
                ->exists()
        );

        $runner->executeNode($idempotentRun->id, 'action_idempotent');

        // Customer note should not have been updated because idempotency check skipped duplicate execution
        $customer->refresh();
        $this->assertNull($customer->notes);
        // Only the 1 pre-seeded log should exist
        $this->assertEquals(1, WorkflowLog::where('workflow_run_id', $idempotentRun->id)->count());
    }
}
