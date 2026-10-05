<?php

namespace Tests\Feature\Workflow;

use App\Domain\Booking\Actions\CreateBooking;
use App\Domain\Booking\Enums\AllocationStatus;
use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Events\BookingCreated;
use App\Domain\Booking\Events\BookingStatusChanged;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Models\BookingAllocation;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Customer\Models\Customer;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
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
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Tenant\TenantIsolationTestHelper;

uses(RefreshDatabase::class, TenantIsolationTestHelper::class);

beforeEach(function () {
    Carbon::setTestNow('2026-10-05 10:00:00');
    $this->seed(RolePermissionSeeder::class);
    TenantContext::clear();
});

afterEach(function () {
    TenantContext::clear();
});

test('BookingCreated domain event triggers matching active workflow and executes nodes synchronously', function () {
    $env = $this->createTenantEnvironment('Salon Cantik');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Faqih Customer',
        'phone_e164' => '+6281234567890',
    ]);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Haircut Treatment',
        'price_idr' => 150000,
    ]);

    // Create workflow with trigger and action
    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Otomasi Booking Salon',
        'service_id' => $service->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'trigger_1',
                'type' => 'trigger',
                'data' => [
                    'category' => 'trigger',
                    'type' => 'booking_created',
                    'label' => 'Saat Booking Dibuat',
                    'config' => [],
                ],
            ],
            [
                'id' => 'action_1',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'send_notification',
                    'label' => 'Kirim Notifikasi WA',
                    'config' => [
                        'channel' => 'WHATSAPP',
                        'message' => 'Halo {customer_name}, booking {booking_code} berhasil dibuat!',
                    ],
                ],
            ],
        ],
        'edges' => [
            [
                'id' => 'e1',
                'source' => 'trigger_1',
                'target' => 'action_1',
            ],
        ],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $workflow->update(['current_version_id' => $version->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'workflow_version_id' => $version->id,
        'total_idr' => 150000,
    ]);

    // Dispatch domain event
    event(new BookingCreated($booking));

    // Verify WorkflowRun was created and completed
    $run = WorkflowRun::where('booking_id', $booking->id)->first();
    expect($run)->not->toBeNull();
    expect($run->status)->toBe(WorkflowRun::STATUS_COMPLETED);
    expect($run->trigger_event)->toBe('booking.created');

    // Verify logs
    $logs = WorkflowLog::where('workflow_run_id', $run->id)->get();
    expect($logs->count())->toBe(2);

    $triggerLog = $logs->where('node_id', 'trigger_1')->first();
    expect($triggerLog)->not->toBeNull();
    expect($triggerLog->status)->toBe(WorkflowLog::STATUS_SUCCESS);

    $actionLog = $logs->where('node_id', 'action_1')->first();
    expect($actionLog)->not->toBeNull();
    expect($actionLog->status)->toBe(WorkflowLog::STATUS_SUCCESS);
    expect($actionLog->output_data['notification_dispatched'])->toBeTrue();
});

test('booking is bound to published workflow_version_id upon creation for immutability (Prompt 3.4)', function () {
    $env = $this->createTenantEnvironment('Klinik Estetika');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'phone_e164' => '+6281299998888',
    ]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    // Setup active workflow with Version 1
    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workflow Immutability Test',
        'service_id' => $service->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    $v1 = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => ['nodes' => [], 'edges' => []],
        'published_at' => now(),
    ]);

    $workflow->update(['current_version_id' => $v1->id]);

    // Setup operating hours and resource for CreateBooking action
    $resourceType = ResourceType::create([
        'tenant_id' => $tenant->id,
        'code' => 'room',
        'name' => 'Room',
        'icon' => 'door',
        'is_staff' => false,
        'is_space' => true,
        'is_equipment' => false,
        'is_active' => true,
    ]);

    $resource = Resource::factory()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $resourceType->id,
    ]);

    $service->resourceRules()->create([
        'tenant_id' => $tenant->id,
        'resource_type_id' => $resourceType->id,
        'quantity_required' => 1,
    ]);

    for ($d = 0; $d <= 6; $d++) {
        BusinessHour::create([
            'tenant_id' => $tenant->id,
            'business_id' => $env['business']->id,
            'day_of_week' => $d,
            'open_time' => '08:00:00',
            'close_time' => '20:00:00',
            'is_closed' => false,
        ]);
    }

    /** @var CreateBooking $action */
    $action = app(CreateBooking::class);
    $booking = $action->execute([
        'tenant' => $tenant,
        'service' => $service,
        'customer' => ['name' => 'Budi Santoso', 'phone' => '081299998888'],
        'start_at' => '2026-10-06 10:00:00',
        'requires_payment' => false,
    ]);

    expect($booking->workflow_version_id)->toBe($v1->id);

    // Now update workflow to Version 2
    $v2 = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 2,
        'status' => 'PUBLISHED',
        'graph' => ['nodes' => [], 'edges' => []],
        'published_at' => now(),
    ]);
    $workflow->update(['current_version_id' => $v2->id]);

    // The existing booking MUST remain bound to Version 1
    $booking->refresh();
    expect($booking->workflow_version_id)->toBe($v1->id);
});

test('BookingStatusChanged domain event triggers workflow with matching target status', function () {
    $env = $this->createTenantEnvironment('Car Wash Express');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Status Changed Workflow',
        'service_id' => $service->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'trigger_status',
                'type' => 'trigger',
                'data' => [
                    'category' => 'trigger',
                    'type' => 'booking_status_changed',
                    'label' => 'Status Berubah Jadi CONFIRMED',
                    'config' => [
                        'to_status' => 'CONFIRMED',
                    ],
                ],
            ],
            [
                'id' => 'action_note',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'add_customer_note',
                    'label' => 'Catat Pelanggan Terkonfirmasi',
                    'config' => [
                        'note' => 'Booking berhasil dikonfirmasi.',
                    ],
                ],
            ],
        ],
        'edges' => [
            [
                'id' => 'e_status',
                'source' => 'trigger_status',
                'target' => 'action_note',
            ],
        ],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);
    $workflow->update(['current_version_id' => $version->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'workflow_version_id' => $version->id,
    ]);

    // Transition via BookingStateMachine: PENDING -> CONFIRMED
    /** @var BookingStateMachine $stateMachine */
    $stateMachine = app(BookingStateMachine::class);
    $stateMachine->transition($booking, BookingStatusCategory::CONFIRMED);

    // Verify WorkflowRun was triggered and customer note updated
    $run = WorkflowRun::where('booking_id', $booking->id)
        ->where('trigger_event', 'booking.status_changed')
        ->first();

    expect($run)->not->toBeNull();
    expect($run->status)->toBe(WorkflowRun::STATUS_COMPLETED);

    $customer->refresh();
    expect($customer->notes)->toContain('Booking berhasil dikonfirmasi.');
});

test('max depth limit (50) stops recursive cyclic execution and marks run as FAILED without infinite loop', function () {
    $env = $this->createTenantEnvironment('Loop Safety Lab');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cyclic Infinite Loop Workflow',
        'service_id' => $service->id,
        'is_default' => true,
        'is_active' => true,
    ]);

    // Create an intentional cyclic graph: node_a -> node_b -> node_a
    $graph = [
        'nodes' => [
            [
                'id' => 'node_a',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'custom',
                    'label' => 'Step A',
                    'config' => [],
                ],
            ],
            [
                'id' => 'node_b',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'custom',
                    'label' => 'Step B',
                    'config' => [],
                ],
            ],
        ],
        'edges' => [
            ['id' => 'e1', 'source' => 'node_a', 'target' => 'node_b'],
            ['id' => 'e2', 'source' => 'node_b', 'target' => 'node_a'],
        ],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'workflow_version_id' => $version->id,
    ]);

    $run = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_loop_test',
        'trigger_event' => 'manual',
        'trigger_payload' => [],
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'node_a',
        'depth' => 49, // Simulating depth close to limit
        'started_at' => now(),
    ]);

    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);
    $runner->executeNode($run->id, 'node_a');

    $run->refresh();
    expect($run->status)->toBe(WorkflowRun::STATUS_FAILED);
    expect($run->error_message)->toContain('Batas kedalaman eksekusi alur terlampaui');
});

test('action idempotency prevents re-executing already succeeded nodes in the same run', function () {
    $env = $this->createTenantEnvironment('Idempotency Studio');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Idempotency Workflow',
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'action_idempotent',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'add_customer_note',
                    'label' => 'Tambah Catatan Sekali',
                    'config' => ['note' => 'Hanya dicatat satu kali'],
                ],
            ],
        ],
        'edges' => [],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'workflow_version_id' => $version->id,
    ]);

    $run = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_idem_1',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'action_idempotent',
        'depth' => 0,
        'started_at' => now(),
    ]);

    // Seed existing SUCCESS log
    WorkflowLog::create([
        'tenant_id' => $tenant->id,
        'workflow_run_id' => $run->id,
        'node_id' => 'action_idempotent',
        'node_type' => 'action',
        'node_label' => 'Tambah Catatan Sekali',
        'status' => WorkflowLog::STATUS_SUCCESS,
        'attempt' => 1,
        'executed_at' => now(),
    ]);

    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);
    $runner->executeNode($run->id, 'action_idempotent');

    // Customer note should not have been updated because idempotency check skipped execution
    $customer->refresh();
    expect($customer->notes)->toBeNull();
    // Only the pre-seeded log should exist
    expect(WorkflowLog::where('workflow_run_id', $run->id)->count())->toBe(1);
});

test('condition node evaluates expressions and branches correctly to yes or no handle', function () {
    $env = $this->createTenantEnvironment('Barbershop VIP');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'High Value Booking Branching',
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'cond_price',
                'type' => 'condition',
                'data' => [
                    'category' => 'condition',
                    'type' => 'booking_price',
                    'label' => 'Total > 500k',
                    'config' => [
                        'field' => 'booking.total_price',
                        'operator' => '>',
                        'value' => 500000,
                    ],
                ],
            ],
            [
                'id' => 'action_vip',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'add_customer_note',
                    'label' => 'Catat VIP',
                    'config' => ['note' => 'Pelanggan VIP'],
                ],
            ],
            [
                'id' => 'action_regular',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'add_customer_note',
                    'label' => 'Catat Reguler',
                    'config' => ['note' => 'Pelanggan Reguler'],
                ],
            ],
        ],
        'edges' => [
            ['id' => 'e_yes', 'source' => 'cond_price', 'target' => 'action_vip', 'sourceHandle' => 'yes'],
            ['id' => 'e_no', 'source' => 'cond_price', 'target' => 'action_regular', 'sourceHandle' => 'no'],
        ],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);

    // Test Case 1: High value (750k) -> branches to yes (action_vip)
    $vipBooking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'total_idr' => 750000,
        'workflow_version_id' => $version->id,
    ]);

    $runVip = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $vipBooking->id,
        'execution_id' => 'run_vip_1',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'cond_price',
        'depth' => 0,
        'started_at' => now(),
    ]);

    $runner->executeNode($runVip->id, 'cond_price');

    $vipLog = WorkflowLog::where('workflow_run_id', $runVip->id)->where('node_id', 'action_vip')->first();
    $regularLog = WorkflowLog::where('workflow_run_id', $runVip->id)->where('node_id', 'action_regular')->first();

    expect($vipLog)->not->toBeNull();
    expect($regularLog)->toBeNull();
    expect($customer->fresh()->notes)->toContain('Pelanggan VIP');

    // Clear notes for test case 2
    $customer->update(['notes' => null]);

    // Test Case 2: Low value (200k) -> branches to no (action_regular)
    $regBooking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'total_idr' => 200000,
        'workflow_version_id' => $version->id,
    ]);

    $runReg = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $regBooking->id,
        'execution_id' => 'run_reg_1',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'cond_price',
        'depth' => 0,
        'started_at' => now(),
    ]);

    $runner->executeNode($runReg->id, 'cond_price');

    $vipLog2 = WorkflowLog::where('workflow_run_id', $runReg->id)->where('node_id', 'action_vip')->first();
    $regularLog2 = WorkflowLog::where('workflow_run_id', $runReg->id)->where('node_id', 'action_regular')->first();

    expect($vipLog2)->toBeNull();
    expect($regularLog2)->not->toBeNull();
    expect($customer->fresh()->notes)->toContain('Pelanggan Reguler');
});

test('change_status action strictly executes via BookingStateMachine', function () {
    $env = $this->createTenantEnvironment('Klinik Gigi Sehat');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Auto Confirm Workflow',
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'action_confirm',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'change_status',
                    'label' => 'Ubah Status ke CONFIRMED',
                    'config' => [
                        'status' => 'CONFIRMED',
                    ],
                ],
            ],
        ],
        'edges' => [],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::PENDING,
        'workflow_version_id' => $version->id,
    ]);

    $run = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_state_machine_test',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'action_confirm',
        'depth' => 0,
        'started_at' => now(),
    ]);

    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);
    $runner->executeNode($run->id, 'action_confirm');

    $booking->refresh();
    expect($booking->status_category)->toBe(BookingStatusCategory::CONFIRMED);

    // Verify status history was created via BookingStateMachine
    $this->assertDatabaseHas('booking_status_history', [
        'booking_id' => $booking->id,
        'from_category' => 'PENDING',
        'to_category' => 'CONFIRMED',
        'actor_type' => 'workflow',
    ]);
});

test('delay node pauses execution in WAITING_DELAY and resumes via workflows:process-delays console command', function () {
    $env = $this->createTenantEnvironment('Spa Aromatherapy');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Delayed Reminder Workflow',
        'is_default' => true,
        'is_active' => true,
    ]);

    $graph = [
        'nodes' => [
            [
                'id' => 'delay_node',
                'type' => 'delay',
                'data' => [
                    'category' => 'delay',
                    'type' => 'delay',
                    'label' => 'Tunggu 15 Menit',
                    'config' => [
                        'duration' => 15,
                        'unit' => 'minutes',
                    ],
                ],
            ],
            [
                'id' => 'after_delay_action',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'add_customer_note',
                    'label' => 'Catat Setelah Delay',
                    'config' => ['note' => 'Reminder 15m selesai'],
                ],
            ],
        ],
        'edges' => [
            ['id' => 'e_delay', 'source' => 'delay_node', 'target' => 'after_delay_action'],
        ],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'workflow_version_id' => $version->id,
    ]);

    $run = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_delay_test',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'delay_node',
        'depth' => 0,
        'started_at' => now(),
    ]);

    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);
    $runner->executeNode($run->id, 'delay_node');

    // 1. Verify log is in WAITING_DELAY state with run_at in the future
    $delayLog = WorkflowLog::where('workflow_run_id', $run->id)->where('node_id', 'delay_node')->first();
    expect($delayLog)->not->toBeNull();
    expect($delayLog->status)->toBe(WorkflowLog::STATUS_WAITING_DELAY);
    expect($delayLog->run_at)->not->toBeNull();

    // The next action must not have executed yet
    expect(WorkflowLog::where('workflow_run_id', $run->id)->where('node_id', 'after_delay_action')->exists())->toBeFalse();

    // 2. Run console command before due time -> 0 processed
    $exitCodeBefore = Artisan::call('workflows:process-delays');
    expect($exitCodeBefore)->toBe(0);
    expect($delayLog->fresh()->status)->toBe(WorkflowLog::STATUS_WAITING_DELAY);

    // 3. Travel time forward 20 minutes (past the 15m delay)
    Carbon::setTestNow(now()->addMinutes(20));

    // 4. Run console command again -> processed and resumed
    $exitCodeAfter = Artisan::call('workflows:process-delays');
    expect($exitCodeAfter)->toBe(0);

    // Verify delay log transitioned to SUCCESS
    expect($delayLog->fresh()->status)->toBe(WorkflowLog::STATUS_SUCCESS);

    // Verify child action node was executed
    $afterActionLog = WorkflowLog::where('workflow_run_id', $run->id)->where('node_id', 'after_delay_action')->first();
    expect($afterActionLog)->not->toBeNull();
    expect($afterActionLog->status)->toBe(WorkflowLog::STATUS_SUCCESS);
    expect($customer->fresh()->notes)->toContain('Reminder 15m selesai');
});

test('workflow action failure marks run as FAILED without corrupting or deleting the booking', function () {
    $env = $this->createTenantEnvironment('Safety Clinic');
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Failing Action Workflow',
        'is_default' => true,
        'is_active' => true,
    ]);

    // An invalid transition or malformed action that will trigger an exception
    $graph = [
        'nodes' => [
            [
                'id' => 'invalid_action',
                'type' => 'action',
                'data' => [
                    'category' => 'action',
                    'type' => 'change_status',
                    'label' => 'Invalid Status Transition',
                    'config' => [
                        'status' => 'INVALID_STATUS_CATEGORY_XYZ',
                    ],
                ],
            ],
        ],
        'edges' => [],
    ];

    $version = WorkflowVersion::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status_category' => BookingStatusCategory::CONFIRMED,
        'workflow_version_id' => $version->id,
    ]);

    $run = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_fail_safety',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_RUNNING,
        'current_node_id' => 'invalid_action',
        'depth' => 0,
        'started_at' => now(),
    ]);

    // Pass a nodeId that does not exist in graph to test failure
    /** @var WorkflowRunnerService $runner */
    $runner = app(WorkflowRunnerService::class);
    $runner->executeNode($run->id, 'non_existent_node_id');

    $run->refresh();
    expect($run->status)->toBe(WorkflowRun::STATUS_FAILED);
    expect($run->error_message)->toContain('tidak ditemukan pada definisi graf');

    // Crucial check: Booking entity must be completely safe and intact
    $bookingCheck = Booking::find($booking->id);
    expect($bookingCheck)->not->toBeNull();
    expect($bookingCheck->status_category)->toBe(BookingStatusCategory::CONFIRMED);
});

test('owner can view workflow runs list and manually retry a failed run', function () {
    $env = $this->createTenantEnvironment('Auto Detailing Lab');
    $owner = $env['user'];
    $tenant = $env['tenant'];
    TenantContext::setTenant($tenant);

    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $workflow = Workflow::create([
        'tenant_id' => $tenant->id,
        'name' => 'Retryable Workflow',
        'is_default' => true,
        'is_active' => true,
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
        'status' => 'PUBLISHED',
        'graph' => $graph,
        'published_at' => now(),
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'workflow_version_id' => $version->id,
    ]);

    $failedRun = WorkflowRun::create([
        'tenant_id' => $tenant->id,
        'workflow_id' => $workflow->id,
        'version_id' => $version->id,
        'booking_id' => $booking->id,
        'execution_id' => 'run_failed_for_retry',
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

    // 1. Fetch runs list via owner controller
    $responseList = $this->actingAs($owner)->getJson("/app/workflows/{$workflow->id}/runs");
    $responseList->assertOk();
    $responseList->assertJsonStructure(['data' => [['id', 'execution_id', 'status', 'logs_count']]]);
    expect($responseList->json('data.0.id'))->toBe($failedRun->id);

    // 2. Fetch specific run detail with logs
    $responseDetail = $this->actingAs($owner)->getJson("/app/workflows/runs/{$failedRun->id}");
    $responseDetail->assertOk();
    $responseDetail->assertJsonStructure(['run' => ['id', 'status', 'logs']]);

    // 3. Trigger manual retry
    $responseRetry = $this->actingAs($owner)->postJson("/app/workflows/runs/{$failedRun->id}/retry");
    $responseRetry->assertOk();
    $responseRetry->assertJson(['success' => true]);

    // Since queue is sync, node execution should now succeed!
    $failedRun->refresh();
    expect($failedRun->status)->toBe(WorkflowRun::STATUS_COMPLETED);
    expect($customer->fresh()->notes)->toContain('Berhasil setelah retry manual');
});

test('tenant isolation prevents accessing or retrying runs of other tenants', function () {
    $envA = $this->createTenantEnvironment('Tenant Alfa');
    $ownerA = $envA['user'];
    $tenantA = $envA['tenant'];

    $envB = $this->createTenantEnvironment('Tenant Beta');
    $ownerB = $envB['user'];
    $tenantB = $envB['tenant'];

    TenantContext::setTenant($tenantA);

    $workflowA = Workflow::create([
        'tenant_id' => $tenantA->id,
        'name' => 'Alfa Workflow',
        'is_default' => true,
        'is_active' => true,
    ]);

    $versionA = WorkflowVersion::create([
        'tenant_id' => $tenantA->id,
        'workflow_id' => $workflowA->id,
        'version_number' => 1,
        'status' => 'PUBLISHED',
        'graph' => ['nodes' => [], 'edges' => []],
        'published_at' => now(),
    ]);

    $runA = WorkflowRun::create([
        'tenant_id' => $tenantA->id,
        'workflow_id' => $workflowA->id,
        'version_id' => $versionA->id,
        'execution_id' => 'run_alfa_secret',
        'trigger_event' => 'manual',
        'status' => WorkflowRun::STATUS_FAILED,
        'depth' => 0,
        'started_at' => now(),
    ]);

    // Tenant B owner attempts to view runs of Workflow A
    $responseList = $this->actingAs($ownerB)->getJson("/app/workflows/{$workflowA->id}/runs");
    $responseList->assertStatus(404);

    // Tenant B owner attempts to view run detail of Run A
    $responseDetail = $this->actingAs($ownerB)->getJson("/app/workflows/runs/{$runA->id}");
    $responseDetail->assertStatus(404);

    // Tenant B owner attempts to retry run of Run A
    $responseRetry = $this->actingAs($ownerB)->postJson("/app/workflows/runs/{$runA->id}/retry");
    $responseRetry->assertStatus(404);
});
