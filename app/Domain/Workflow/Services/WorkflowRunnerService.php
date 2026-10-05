<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\Booking;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Customer\Models\Customer;
use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Enums\NotificationEvent;
use App\Domain\Notification\Services\NotificationService;
use App\Domain\Notification\Services\TemplateParser;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Workflow\Exceptions\WorkflowException;
use App\Domain\Workflow\Jobs\ExecuteWorkflowNodeJob;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowLog;
use App\Domain\Workflow\Models\WorkflowRun;
use App\Domain\Workflow\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WorkflowRunnerService
{
    public const MAX_EXECUTION_DEPTH = 50;

    public function __construct(
        protected BookingStateMachine $stateMachine,
        protected NotificationService $notificationService,
        protected TemplateParser $templateParser
    ) {}

    /**
     * Dispatch domain event to trigger matching active workflows (PRD 62, 204.5).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleEvent(string $triggerEvent, array $payload, ?Booking $booking = null): ?WorkflowRun
    {
        $tenantId = $booking ? $booking->tenant_id : (int) ($payload['tenant_id'] ?? 0);
        if (! $tenantId) {
            return null;
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return null;
        }

        // 1. Resolve workflow and immutable version
        /** @var WorkflowVersion|null $version */
        $version = null;
        /** @var Workflow|null $workflow */
        $workflow = null;

        if ($booking && $booking->workflow_version_id) {
            $version = WorkflowVersion::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($booking->workflow_version_id);
            if ($version) {
                $workflow = $version->workflow;
            }
        }

        if (! $version) {
            // Find active workflow for specific service or tenant default
            $serviceId = $booking ? $booking->service_id : ($payload['service_id'] ?? null);

            $workflow = Workflow::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
                ->where('is_active', true)
                ->first();

            if (! $workflow) {
                $workflow = Workflow::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_default', true)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $workflow) {
                return null;
            }

            $version = $workflow->currentVersion;
            if (! $version) {
                return null;
            }

            // Bind booking to this workflow version for immutability (Prompt 3.4)
            if ($booking && ! $booking->workflow_version_id) {
                $booking->update(['workflow_version_id' => $version->id]);
            }
        }

        $graph = $version->graph;
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];

        if (empty($nodes)) {
            return null;
        }

        // 2. Locate matching trigger node in the graph
        $matchingTriggerNode = null;
        foreach ($nodes as $node) {
            $nodeType = strtolower((string) ($node['type'] ?? $node['data']['category'] ?? ''));
            $subType = strtolower((string) ($node['data']['type'] ?? ''));

            if ($nodeType === 'trigger' || str_starts_with($subType, 'trigger_')) {
                if ($this->doesTriggerMatch($triggerEvent, $subType, $node['data']['config'] ?? [], $payload)) {
                    $matchingTriggerNode = $node;
                    break;
                }
            }
        }

        if (! $matchingTriggerNode) {
            return null;
        }

        $triggerNodeId = (string) $matchingTriggerNode['id'];
        $executionId = 'run_' . Str::random(24);

        // 3. Create WorkflowRun
        /** @var WorkflowRun $run */
        $run = WorkflowRun::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'version_id' => $version->id,
            'booking_id' => $booking?->id,
            'execution_id' => $executionId,
            'trigger_event' => $triggerEvent,
            'trigger_payload' => $payload,
            'status' => WorkflowRun::STATUS_RUNNING,
            'current_node_id' => $triggerNodeId,
            'depth' => 0,
            'started_at' => now(),
        ]);

        // 4. Log trigger execution as SUCCESS
        WorkflowLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'workflow_run_id' => $run->id,
            'node_id' => $triggerNodeId,
            'node_type' => 'trigger',
            'node_label' => $matchingTriggerNode['data']['label'] ?? 'Pemicu Alur Kerja',
            'status' => WorkflowLog::STATUS_SUCCESS,
            'attempt' => 1,
            'input_data' => $payload,
            'output_data' => ['triggered' => true, 'timestamp' => now()->toIso8601String()],
            'executed_at' => now(),
        ]);

        // 5. Dispatch outgoing nodes connected to the trigger
        $outgoingEdges = array_filter($edges, fn ($e) => ($e['source'] ?? '') === $triggerNodeId);
        if (empty($outgoingEdges)) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return $run;
        }

        foreach ($outgoingEdges as $edge) {
            $targetNodeId = (string) ($edge['target'] ?? '');
            if ($targetNodeId) {
                ExecuteWorkflowNodeJob::dispatch($run->id, $targetNodeId, $payload);
            }
        }

        return $run;
    }

    /**
     * Execute a specific node in a workflow run (invoked via queue job).
     *
     * @param  array<string, mixed>  $statePayload
     */
    public function executeNode(int $runId, string $nodeId, array $statePayload = []): void
    {
        /** @var WorkflowRun|null $run */
        $run = WorkflowRun::withoutGlobalScopes()
            ->with(['version', 'booking'])
            ->find($runId);

        if (! $run || $run->status === WorkflowRun::STATUS_CANCELLED) {
            return;
        }

        $graph = $run->version->graph;
        $nodes = $graph['nodes'];
        $edges = $graph['edges'];

        // 1. Safety Check: Max depth / loop protection (PRD 62, 204.5)
        $currentDepth = (int) $run->depth + 1;
        $run->update(['depth' => $currentDepth]);

        if ($currentDepth > self::MAX_EXECUTION_DEPTH) {
            $errorMsg = "Batas kedalaman eksekusi alur terlampaui (maksimal " . self::MAX_EXECUTION_DEPTH . " langkah). Terdeteksi potensi perulangan (infinite loop).";
            $run->update([
                'status' => WorkflowRun::STATUS_FAILED,
                'error_message' => $errorMsg,
                'completed_at' => now(),
            ]);

            WorkflowLog::withoutGlobalScopes()->create([
                'tenant_id' => $run->tenant_id,
                'workflow_run_id' => $run->id,
                'node_id' => $nodeId,
                'node_type' => 'error',
                'node_label' => 'Batas Kedalaman Alur Terlampaui',
                'status' => WorkflowLog::STATUS_FAILED,
                'attempt' => 1,
                'error_message' => $errorMsg,
                'executed_at' => now(),
            ]);

            Log::error("[WorkflowRunner] Max depth exceeded for run {$run->id} at node {$nodeId}");

            return;
        }

        // 2. Safety Check: Idempotency (PRD 62, 204.5)
        $alreadySucceeded = WorkflowLog::withoutGlobalScopes()
            ->where('workflow_run_id', $run->id)
            ->where('node_id', $nodeId)
            ->where('status', WorkflowLog::STATUS_SUCCESS)
            ->exists();

        if ($alreadySucceeded) {
            Log::info("[WorkflowRunner] Node {$nodeId} already succeeded for run {$run->id}. Skipping duplicate execution.");

            return;
        }

        // 3. Find node definition in graph
        $targetNode = null;
        foreach ($nodes as $node) {
            if ((string) ($node['id'] ?? '') === $nodeId) {
                $targetNode = $node;
                break;
            }
        }

        if (! $targetNode) {
            $errorMsg = "Node '{$nodeId}' tidak ditemukan pada definisi graf.";
            $run->update([
                'status' => WorkflowRun::STATUS_FAILED,
                'error_message' => $errorMsg,
            ]);

            return;
        }

        $nodeCategory = strtolower((string) ($targetNode['type'] ?? $targetNode['data']['category'] ?? 'action'));
        $nodeSubType = strtolower((string) ($targetNode['data']['type'] ?? ''));
        $nodeLabel = (string) ($targetNode['data']['label'] ?? $nodeId);
        $nodeConfig = (array) ($targetNode['data']['config'] ?? []);

        // Count previous attempts
        $previousAttempts = WorkflowLog::withoutGlobalScopes()
            ->where('workflow_run_id', $run->id)
            ->where('node_id', $nodeId)
            ->count();
        $attempt = $previousAttempts + 1;

        $run->update(['current_node_id' => $nodeId]);

        // 4. Execute node according to category
        try {
            $outputData = [];
            $nextEdges = [];

            if ($nodeCategory === 'condition') {
                $isConditionMet = $this->evaluateCondition($targetNode, $run->booking, $statePayload);
                $branch = $isConditionMet ? 'yes' : 'no';
                $outputData = [
                    'condition_result' => $isConditionMet,
                    'chosen_branch' => $branch,
                ];

                // Match outgoing edge for chosen handle
                $candidateEdges = array_values(array_filter($edges, fn ($e) => ($e['source'] ?? '') === $nodeId));
                foreach ($candidateEdges as $edge) {
                    $sourceHandle = strtolower((string) ($edge['sourceHandle'] ?? $edge['handle'] ?? ''));
                    if ($branch === 'yes' && in_array($sourceHandle, ['yes', 'true', 'ya', '1'], true)) {
                        $nextEdges[] = $edge;
                    } elseif ($branch === 'no' && in_array($sourceHandle, ['no', 'false', 'tidak', '0'], true)) {
                        $nextEdges[] = $edge;
                    }
                }

                // Fallback: If no edge matched handle explicitly, use first edge if true
                if (empty($nextEdges) && ! empty($candidateEdges)) {
                    if ($isConditionMet && isset($candidateEdges[0])) {
                        $nextEdges[] = $candidateEdges[0];
                    } elseif (! $isConditionMet && isset($candidateEdges[1])) {
                        $nextEdges[] = $candidateEdges[1];
                    }
                }
            } elseif ($nodeCategory === 'delay') {
                $duration = (int) ($nodeConfig['duration'] ?? 10);
                $unit = strtolower((string) ($nodeConfig['unit'] ?? 'minutes'));

                $runAt = match ($unit) {
                    'hours', 'jam' => now()->addHours($duration),
                    'days', 'hari' => now()->addDays($duration),
                    default => now()->addMinutes($duration),
                };

                // Create log in WAITING_DELAY state and pause queue propagation
                WorkflowLog::withoutGlobalScopes()->create([
                    'tenant_id' => $run->tenant_id,
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => 'delay',
                    'node_label' => $nodeLabel,
                    'status' => WorkflowLog::STATUS_WAITING_DELAY,
                    'attempt' => $attempt,
                    'input_data' => $statePayload,
                    'output_data' => [
                        'duration' => $duration,
                        'unit' => $unit,
                        'run_at' => $runAt->toIso8601String(),
                    ],
                    'run_at' => $runAt,
                ]);

                return; // Delay is paused until scheduler runs
            } elseif ($nodeCategory === 'action') {
                $outputData = $this->executeAction($nodeSubType, $nodeConfig, $run->booking, $statePayload);
                $nextEdges = array_values(array_filter($edges, fn ($e) => ($e['source'] ?? '') === $nodeId));
            } else {
                // Unknown or pass-through
                $nextEdges = array_values(array_filter($edges, fn ($e) => ($e['source'] ?? '') === $nodeId));
            }

            // Record successful node log
            WorkflowLog::withoutGlobalScopes()->create([
                'tenant_id' => $run->tenant_id,
                'workflow_run_id' => $run->id,
                'node_id' => $nodeId,
                'node_type' => $nodeCategory,
                'node_label' => $nodeLabel,
                'status' => WorkflowLog::STATUS_SUCCESS,
                'attempt' => $attempt,
                'input_data' => $statePayload,
                'output_data' => $outputData,
                'executed_at' => now(),
            ]);

            // Merge output to state payload
            $mergedPayload = array_merge($statePayload, $outputData);

            // 5. Dispatch next nodes if any
            if (empty($nextEdges)) {
                $this->checkAndCompleteRun($run);
            } else {
                foreach ($nextEdges as $edge) {
                    $targetNextId = (string) ($edge['target'] ?? '');
                    if ($targetNextId) {
                        ExecuteWorkflowNodeJob::dispatch($run->id, $targetNextId, $mergedPayload);
                    }
                }
            }
        } catch (Throwable $e) {
            // Non-blocking for booking, but mark workflow node and run as failed (Prompt 3.4)
            Log::error("[WorkflowRunner] Error executing node {$nodeId} in run {$run->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            WorkflowLog::withoutGlobalScopes()->create([
                'tenant_id' => $run->tenant_id,
                'workflow_run_id' => $run->id,
                'node_id' => $nodeId,
                'node_type' => $nodeCategory,
                'node_label' => $nodeLabel,
                'status' => WorkflowLog::STATUS_FAILED,
                'attempt' => $attempt,
                'input_data' => $statePayload,
                'error_message' => $e->getMessage(),
                'executed_at' => now(),
            ]);

            $run->update([
                'status' => WorkflowRun::STATUS_FAILED,
                'error_message' => "Gagal pada simpul '{$nodeLabel}': {$e->getMessage()}",
            ]);
        }
    }

    /**
     * Process pending delayed workflow nodes (run every minute by console scheduler).
     */
    public function processPendingDelays(): int
    {
        $pendingLogs = WorkflowLog::withoutGlobalScopes()
            ->where('status', WorkflowLog::STATUS_WAITING_DELAY)
            ->where('run_at', '<=', now())
            ->with(['run.version'])
            ->get();

        $processedCount = 0;

        foreach ($pendingLogs as $log) {
            /** @var WorkflowRun|null $run */
            $run = $log->run;
            if (! $run || $run->status === WorkflowRun::STATUS_CANCELLED) {
                continue;
            }

            $graph = $run->version->graph;
            $edges = $graph['edges'];

            // Mark delay log as SUCCESS
            $log->update([
                'status' => WorkflowLog::STATUS_SUCCESS,
                'executed_at' => now(),
            ]);

            $processedCount++;

            // Dispatch outgoing edges from the delay node
            $outgoingEdges = array_values(array_filter($edges, fn ($e) => ($e['source'] ?? '') === $log->node_id));

            if (empty($outgoingEdges)) {
                $this->checkAndCompleteRun($run);
            } else {
                foreach ($outgoingEdges as $edge) {
                    $targetNodeId = (string) ($edge['target'] ?? '');
                    if ($targetNodeId) {
                        ExecuteWorkflowNodeJob::dispatch($run->id, $targetNodeId, $log->input_data ?? []);
                    }
                }
            }
        }

        return $processedCount;
    }

    /**
     * Retry a failed workflow run (Prompt 3.4).
     */
    public function retryRun(WorkflowRun $run): WorkflowRun
    {
        $failedLog = $run->logs()
            ->where('status', WorkflowLog::STATUS_FAILED)
            ->latest('id')
            ->first();

        $targetNodeId = $failedLog ? $failedLog->node_id : $run->current_node_id;

        $run->update([
            'status' => WorkflowRun::STATUS_RUNNING,
            'error_message' => null,
            'completed_at' => null,
        ]);

        if ($targetNodeId) {
            ExecuteWorkflowNodeJob::dispatch($run->id, $targetNodeId, $run->trigger_payload ?? []);
        }

        return $run->fresh();
    }

    /**
     * Evaluate condition expression.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $statePayload
     */
    protected function evaluateCondition(array $node, ?Booking $booking, array $statePayload): bool
    {
        $config = (array) ($node['data']['config'] ?? []);
        $subType = strtolower((string) ($node['data']['type'] ?? ''));

        // Shorthand checks
        if ($subType === 'payment_status') {
            $targetPaymentStatus = strtoupper((string) ($config['payment_status'] ?? 'PAID'));
            $actualPaymentStatus = strtoupper((string) ($booking !== null ? $booking->payment_status : ($statePayload['payment_status'] ?? '')));

            return $actualPaymentStatus === $targetPaymentStatus;
        }

        if ($subType === 'booking_status') {
            $targetStatus = strtoupper((string) ($config['status'] ?? 'CONFIRMED'));
            $actualStatus = strtoupper((string) ($booking !== null ? $booking->status_category->value : ($statePayload['status'] ?? '')));

            return $actualStatus === $targetStatus;
        }

        // Generic field comparison
        $field = (string) ($config['field'] ?? 'payment_status');
        $operator = (string) ($config['operator'] ?? 'equals');
        $expectedValue = $config['value'] ?? 'PAID';

        $actualValue = match ($field) {
            'total_idr', 'total_amount_idr', 'amount', 'total_price', 'booking.total_price', 'booking.total_idr' => (int) ($booking !== null ? $booking->total_idr : ($statePayload['total_idr'] ?? $statePayload['total_amount_idr'] ?? $statePayload['total_price'] ?? 0)),
            'payment_status', 'booking.payment_status' => (string) ($booking !== null ? $booking->payment_status : ($statePayload['payment_status'] ?? '')),
            'status', 'status_category', 'booking.status', 'booking.status_category' => (string) ($booking !== null ? $booking->status_category->value : ($statePayload['status'] ?? '')),
            'service_id', 'booking.service_id' => (int) ($booking !== null ? $booking->service_id : ($statePayload['service_id'] ?? 0)),
            default => $statePayload[$field] ?? null,
        };

        return match ($operator) {
            'equals', '==' => (string) $actualValue === (string) $expectedValue,
            'not_equals', '!=' => (string) $actualValue !== (string) $expectedValue,
            'greater_than', '>', 'gt' => (float) $actualValue > (float) $expectedValue,
            'less_than', '<', 'lt' => (float) $actualValue < (float) $expectedValue,
            'greater_than_or_equal', '>=' => (float) $actualValue >= (float) $expectedValue,
            'less_than_or_equal', '<=' => (float) $actualValue <= (float) $expectedValue,
            'contains' => str_contains(strtolower((string) $actualValue), strtolower((string) $expectedValue)),
            default => (string) $actualValue === (string) $expectedValue,
        };
    }

    /**
     * Execute an action node.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $statePayload
     * @return array<string, mixed>
     */
    protected function executeAction(string $subType, array $config, ?Booking $booking, array $statePayload): array
    {
        $result = ['action' => $subType];

        switch ($subType) {
            case 'send_notification':
            case 'action_notification':
                $channel = strtoupper((string) ($config['channel'] ?? 'WHATSAPP'));
                $customMessage = (string) ($config['message'] ?? $config['template'] ?? '');

                if ($booking) {
                    $variables = $this->templateParser->extractBookingVariables($booking);
                    $parsedMessage = $customMessage ? $this->templateParser->parse($customMessage, $variables) : 'Notifikasi alur kerja';

                    // Dispatch notification
                    $this->notificationService->dispatchBookingNotification(
                        $booking,
                        NotificationEvent::BOOKING_CONFIRMED
                    );

                    $result['notification_dispatched'] = true;
                    $result['channel'] = $channel;
                    $result['message_preview'] = Str::limit($parsedMessage, 100);
                }
                break;

            case 'change_status':
            case 'action_status':
                $targetStatusCategory = (string) ($config['status'] ?? $config['to_status'] ?? '');
                if ($booking && $targetStatusCategory) {
                    $categoryEnum = BookingStatusCategory::tryFrom(strtoupper($targetStatusCategory));
                    if ($categoryEnum && $booking->status_category !== $categoryEnum) {
                        // All workflow status transitions MUST go through BookingStateMachine (Prompt 3.4)
                        $this->stateMachine->transition(
                            $booking,
                            $categoryEnum,
                            [
                                'actor_id' => null,
                                'actor_type' => 'workflow',
                                'source' => 'workflow',
                            ]
                        );
                        $result['status_changed_to'] = $categoryEnum->value;
                    }
                }
                break;

            case 'add_customer_note':
            case 'action_note':
                $noteText = (string) ($config['note'] ?? $config['text'] ?? '');
                if ($booking && $booking->customer_id && $noteText) {
                    /** @var Customer|null $customer */
                    $customer = Customer::withoutGlobalScopes()->find($booking->customer_id);
                    if ($customer) {
                        $existingNotes = $customer->notes ?? '';
                        $newNotes = $existingNotes ? ($existingNotes . "\n[" . now()->format('Y-m-d H:i') . "] " . $noteText) : $noteText;
                        $customer->update(['notes' => $newNotes]);
                        $result['customer_note_added'] = true;
                    }
                }
                break;

            default:
                $result['executed'] = true;
                break;
        }

        return $result;
    }

    /**
     * Check if a trigger node matches the domain event and filters.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     */
    protected function doesTriggerMatch(string $event, string $subType, array $config, array $payload): bool
    {
        if ($event === 'booking.created' || $event === 'booking_created') {
            return in_array($subType, ['booking_created', 'trigger_booking_created', 'trigger'], true);
        }

        if ($event === 'booking.status_changed' || $event === 'booking_status_changed') {
            if (! in_array($subType, ['booking_status_changed', 'status_changed', 'trigger_status_changed'], true)) {
                return false;
            }

            // If trigger node configured a specific status filter, verify it matches
            $configuredStatus = strtoupper((string) ($config['status'] ?? $config['target_status'] ?? ''));
            if ($configuredStatus) {
                $actualStatus = strtoupper((string) ($payload['to_category'] ?? $payload['status'] ?? ''));

                return $configuredStatus === $actualStatus;
            }

            return true;
        }

        if ($event === 'manual') {
            return true;
        }

        return false;
    }

    /**
     * Check if workflow run has no more active jobs and mark COMPLETED.
     */
    protected function checkAndCompleteRun(WorkflowRun $run): void
    {
        $hasPending = WorkflowLog::withoutGlobalScopes()
            ->where('workflow_run_id', $run->id)
            ->whereIn('status', [WorkflowLog::STATUS_PENDING, WorkflowLog::STATUS_RUNNING, WorkflowLog::STATUS_WAITING_DELAY])
            ->exists();

        if (! $hasPending && $run->status === WorkflowRun::STATUS_RUNNING) {
            $run->update([
                'status' => WorkflowRun::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }
}
