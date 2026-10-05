<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowService
{
    /**
     * Get all workflows for tenant with their current published and draft versions.
     *
     * @return Collection<int, Workflow>
     */
    public function getWorkflowsForTenant(Tenant $tenant): Collection
    {
        return Workflow::where('tenant_id', $tenant->id)
            ->with(['service', 'currentVersion', 'draftVersion'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Resolve active workflow for a specific service or fallback to default.
     */
    public function getWorkflowForService(int $tenantId, ?int $serviceId = null): ?Workflow
    {
        if ($serviceId) {
            /** @var Workflow|null $serviceWorkflow */
            $serviceWorkflow = Workflow::where('tenant_id', $tenantId)
                ->where('service_id', $serviceId)
                ->where('is_active', true)
                ->with('currentVersion')
                ->first();

            if ($serviceWorkflow && $serviceWorkflow->currentVersion) {
                return $serviceWorkflow;
            }
        }

        /** @var Workflow|null $defaultWorkflow */
        $defaultWorkflow = Workflow::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->with('currentVersion')
            ->first();

        if ($defaultWorkflow && $defaultWorkflow->currentVersion) {
            return $defaultWorkflow;
        }

        /** @var Workflow|null $anyWorkflow */
        $anyWorkflow = Workflow::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('currentVersion')
            ->first();

        return $anyWorkflow;
    }

    /**
     * Validate workflow graph structure (PRD 204.5).
     * Rules:
     * 1. Must contain at least 1 trigger node.
     * 2. No orphan non-trigger nodes (must be reachable from trigger).
     * 3. No infinite cycles / loops (DAG check).
     *
     * @param  array{nodes?: array<int, mixed>, edges?: array<int, mixed>}  $graph
     * @return array{is_valid: bool, errors: array<int, string>}
     */
    public function validateGraph(array $graph): array
    {
        $errors = [];
        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        if (empty($nodes)) {
            return [
                'is_valid' => false,
                'errors' => ['Alur kerja harus memiliki setidaknya satu node.'],
            ];
        }

        // Index nodes by ID
        $nodeMap = [];
        $triggerNodes = [];

        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');
            if (! $nodeId) {
                continue;
            }
            $nodeMap[$nodeId] = $node;

            $nodeType = strtolower((string) ($node['type'] ?? $node['data']['category'] ?? ''));
            $subType = strtolower((string) ($node['data']['type'] ?? ''));

            if ($nodeType === 'trigger' || str_starts_with($subType, 'trigger_') || str_starts_with($nodeId, 'trigger')) {
                $triggerNodes[] = $nodeId;
            }
        }

        // Rule 1: Must contain at least 1 trigger node
        if (empty($triggerNodes)) {
            $errors[] = 'Alur kerja wajib memiliki setidaknya satu node Pemicu (Trigger).';
        }

        // Build adjacency list and in-degree map
        $adj = [];
        $inDegree = [];
        foreach (array_keys($nodeMap) as $id) {
            $adj[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($edges as $edge) {
            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            if ($source && $target && isset($nodeMap[$source]) && isset($nodeMap[$target])) {
                $adj[$source][] = $target;
                $inDegree[$target]++;
            }
        }

        // Rule 2: Check for orphan non-trigger nodes
        foreach ($nodeMap as $id => $node) {
            $nodeType = strtolower((string) ($node['type'] ?? $node['data']['category'] ?? ''));
            $subType = strtolower((string) ($node['data']['type'] ?? ''));
            $isTrigger = ($nodeType === 'trigger' || str_starts_with($subType, 'trigger_') || str_starts_with($id, 'trigger'));

            if (! $isTrigger && ($inDegree[$id] ?? 0) === 0) {
                $nodeLabel = $node['data']['label'] ?? $id;
                $errors[] = "Node '{$nodeLabel}' tidak terhubung (yatim/orphan). Setiap aksi atau kondisi harus memiliki jalur dari pemicu.";
            }
        }

        // Rule 3: Cycle detection using Kahn's algorithm (Topological sort)
        $q = [];
        $tempInDegree = $inDegree;

        foreach ($tempInDegree as $id => $deg) {
            if ($deg === 0) {
                $q[] = $id;
            }
        }

        $visitedCount = 0;
        while (! empty($q)) {
            $curr = array_shift($q);
            $visitedCount++;

            foreach ($adj[$curr] ?? [] as $neighbor) {
                $tempInDegree[$neighbor]--;
                if ($tempInDegree[$neighbor] === 0) {
                    $q[] = $neighbor;
                }
            }
        }

        if ($visitedCount < count($nodeMap)) {
            $errors[] = 'Graf alur kerja mengandung perulangan siklus (loop) tak terbatas. Pastikan alur berjalan satu arah tanpa kembali ke simpul awal.';
        }

        return [
            'is_valid' => empty($errors),
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Save draft graph for a workflow.
     *
     * @param  array{nodes?: array<int, mixed>, edges?: array<int, mixed>}  $graph
     */
    public function saveDraft(Workflow $workflow, array $graph, ?int $userId = null): WorkflowVersion
    {
        /** @var WorkflowVersion|null $draft */
        $draft = WorkflowVersion::where('tenant_id', $workflow->tenant_id)
            ->where('workflow_id', $workflow->id)
            ->where('status', 'DRAFT')
            ->first();

        if ($draft) {
            $draft->update([
                'graph' => $graph,
                'created_by' => $userId ?? $draft->created_by,
            ]);

            return $draft;
        }

        // Compute next version number based on highest version
        $maxVersion = (int) WorkflowVersion::where('tenant_id', $workflow->tenant_id)
            ->where('workflow_id', $workflow->id)
            ->max('version_number');

        return WorkflowVersion::create([
            'tenant_id' => $workflow->tenant_id,
            'workflow_id' => $workflow->id,
            'version_number' => $maxVersion + 1,
            'status' => 'DRAFT',
            'graph' => $graph,
            'created_by' => $userId,
        ]);
    }

    /**
     * Publish workflow graph to immutable version (PRD 23).
     *
     * @throws BookingException
     */
    public function publishVersion(Workflow $workflow, ?int $versionId = null, ?int $userId = null): WorkflowVersion
    {
        return DB::transaction(function () use ($workflow, $versionId, $userId) {
            $targetVersion = $versionId
                ? WorkflowVersion::where('tenant_id', $workflow->tenant_id)
                    ->where('workflow_id', $workflow->id)
                    ->find($versionId)
                : WorkflowVersion::where('tenant_id', $workflow->tenant_id)
                    ->where('workflow_id', $workflow->id)
                    ->where('status', 'DRAFT')
                    ->latest('id')
                    ->first();

            if (! $targetVersion) {
                throw BookingException::validationFailed('Tidak ada draf alur kerja yang dapat dipublikasikan.');
            }

            // Validate graph integrity before publishing
            $validation = $this->validateGraph($targetVersion->graph);
            if (! $validation['is_valid']) {
                $firstError = $validation['errors'][0] ?? 'Struktur alur kerja tidak valid.';
                throw BookingException::validationFailed($firstError);
            }

            // Mark previous published versions as ARCHIVED (or keep immutable history)
            WorkflowVersion::where('tenant_id', $workflow->tenant_id)
                ->where('workflow_id', $workflow->id)
                ->where('status', 'PUBLISHED')
                ->where('id', '!=', $targetVersion->id)
                ->update(['status' => 'ARCHIVED']);

            // Set target version as PUBLISHED
            $targetVersion->status = 'PUBLISHED';
            $targetVersion->published_at = Carbon::now();
            if ($userId) {
                $targetVersion->created_by = $userId;
            }
            $targetVersion->save();

            // Link workflow to this new immutable published version
            $workflow->update([
                'current_version_id' => $targetVersion->id,
            ]);

            return $targetVersion;
        });
    }

    /**
     * Simulate a test run of the workflow graph against mock booking data (PRD 66).
     *
     * @param  array{nodes?: array<int, mixed>, edges?: array<int, mixed>}  $graph
     * @param  array<string, mixed>  $mockPayload
     * @return array{
     *     success: bool,
     *     execution_id: string,
     *     completed_at: string,
     *     steps: array<int, array{
     *         node_id: string,
     *         title: string,
     *         type: string,
     *         status: string,
     *         detail: string
     *     }>,
     *     final_status: string|null,
     *     notifications: array<int, string>
     * }
     */
    public function testDryRun(Workflow $workflow, array $graph, array $mockPayload): array
    {
        $executionId = 'sim_' . Str::random(12);
        $completedAt = now()->toIso8601String();

        $validation = $this->validateGraph($graph);
        if (! $validation['is_valid']) {
            return [
                'success' => false,
                'execution_id' => $executionId,
                'completed_at' => $completedAt,
                'steps' => [
                    [
                        'node_id' => 'error',
                        'title' => 'Validasi Graf Gagal',
                        'type' => 'error',
                        'status' => 'FAILED',
                        'detail' => $validation['errors'][0] ?? 'Graf tidak valid.',
                    ],
                ],
                'final_status' => null,
                'notifications' => [],
            ];
        }

        $nodes = $graph['nodes'] ?? [];
        $edges = $graph['edges'] ?? [];

        $nodeMap = [];
        $triggerId = null;

        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            $nodeMap[$id] = $node;

            $nodeType = $node['type'] ?? $node['data']['category'] ?? '';
            $subType = $node['data']['type'] ?? '';

            if (($nodeType === 'trigger' || str_starts_with((string) $subType, 'trigger_')) && ! $triggerId) {
                $triggerId = $id;
            }
        }

        // Adjacency edges with handle metadata
        $outgoing = [];
        foreach ($edges as $edge) {
            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');
            $sourceHandle = (string) ($edge['sourceHandle'] ?? 'default');

            if (! isset($outgoing[$source])) {
                $outgoing[$source] = [];
            }
            $outgoing[$source][] = [
                'target' => $target,
                'handle' => $sourceHandle,
            ];
        }

        $steps = [];
        $notifications = [];
        $finalStatus = $mockPayload['status'] ?? 'PENDING';

        $currentNodeId = $triggerId;
        $maxDepth = 30;
        $depth = 0;

        while ($currentNodeId && isset($nodeMap[$currentNodeId]) && $depth < $maxDepth) {
            $depth++;
            $node = $nodeMap[$currentNodeId];
            $category = $node['type'] ?? $node['data']['category'] ?? 'action';
            $subType = $node['data']['type'] ?? '';
            $label = $node['data']['label'] ?? 'Node';
            $config = $node['data']['config'] ?? [];

            $selectedHandle = 'default';
            $stepDetail = '';

            if ($category === 'trigger') {
                $stepDetail = "Pemicu '{$label}' terpicu oleh event simulasi.";
            } elseif ($category === 'condition') {
                // Evaluate condition
                $conditionResult = true;
                if ($subType === 'payment_status' || str_contains($subType, 'payment')) {
                    $expected = $config['payment_status'] ?? 'PAID';
                    $actual = $mockPayload['payment_status'] ?? 'UNPAID';
                    $conditionResult = (strtoupper((string) $actual) === strtoupper((string) $expected));
                    $stepDetail = "Cek Status Pembayaran (aktual: {$actual}, diharapkan: {$expected}) -> Hasil: ".($conditionResult ? 'YES' : 'NO');
                } elseif ($subType === 'booking_status') {
                    $expected = $config['status'] ?? 'CONFIRMED';
                    $actual = $mockPayload['status'] ?? 'PENDING';
                    $conditionResult = (strtoupper((string) $actual) === strtoupper((string) $expected));
                    $stepDetail = "Cek Status Booking (aktual: {$actual}, diharapkan: {$expected}) -> Hasil: ".($conditionResult ? 'YES' : 'NO');
                } elseif ($subType === 'total_amount') {
                    $minAmount = (int) ($config['amount'] ?? 0);
                    $actual = (int) ($mockPayload['total_idr'] ?? 0);
                    $conditionResult = ($actual >= $minAmount);
                    $stepDetail = "Cek Total Nilai (aktual: Rp {$actual}, min: Rp {$minAmount}) -> Hasil: ".($conditionResult ? 'YES' : 'NO');
                } else {
                    $stepDetail = "Evaluasi kondisi '{$label}' berhasil.";
                }

                $selectedHandle = $conditionResult ? 'yes' : 'no';
            } elseif ($category === 'action') {
                if ($subType === 'change_status' || ! empty($config['target_status'])) {
                    $newStatus = $config['target_status'] ?? 'CONFIRMED';
                    $finalStatus = $newStatus;
                    $stepDetail = "Status booking dialihkan menjadi '{$newStatus}'.";
                } elseif ($subType === 'send_notification') {
                    $channel = $config['channel'] ?? 'whatsapp';
                    $msg = "Notifikasi {$channel} dijadwalkan ke customer: '{$label}'.";
                    $notifications[] = $msg;
                    $stepDetail = $msg;
                } elseif ($subType === 'assign_resource') {
                    $stepDetail = "Penetapan otomatis staf / ruangan dilakukan.";
                } else {
                    $stepDetail = "Aksi '{$label}' dieksekusi.";
                }
            } elseif ($category === 'delay') {
                $duration = $config['duration'] ?? 10;
                $unit = $config['unit'] ?? 'menit';
                $stepDetail = "Jeda waktu selama {$duration} {$unit} dijadwalkan.";
            }

            $steps[] = [
                'node_id' => $currentNodeId,
                'title' => $label,
                'type' => $category,
                'status' => 'SUCCESS',
                'detail' => $stepDetail,
            ];

            // Resolve next node
            $nodeEdges = $outgoing[$currentNodeId] ?? [];
            $nextNodeId = null;

            if ($category === 'condition') {
                // Find edge matching 'yes' / 'no' handle, or fallback to first
                foreach ($nodeEdges as $edge) {
                    if ($edge['handle'] === $selectedHandle) {
                        $nextNodeId = $edge['target'];
                        break;
                    }
                }
                if (! $nextNodeId && ! empty($nodeEdges)) {
                    $nextNodeId = $nodeEdges[0]['target'];
                }
            } else {
                if (! empty($nodeEdges)) {
                    $nextNodeId = $nodeEdges[0]['target'];
                }
            }

            $currentNodeId = $nextNodeId;
        }

        return [
            'success' => true,
            'execution_id' => $executionId,
            'completed_at' => $completedAt,
            'steps' => $steps,
            'final_status' => $finalStatus,
            'notifications' => $notifications,
        ];
    }

    /**
     * Seed default workflow preset for a tenant (PRD 49).
     */
    public function seedDefaultWorkflowForTenant(Tenant $tenant, string $preset = 'standar'): Workflow
    {
        $presets = $this->getAvailablePresets();
        $selectedPreset = $presets[$preset] ?? $presets['standar'];

        return DB::transaction(function () use ($tenant, $selectedPreset) {
            $workflow = Workflow::create([
                'tenant_id' => $tenant->id,
                'name' => $selectedPreset['name'],
                'description' => $selectedPreset['description'],
                'is_active' => true,
                'is_default' => true,
            ]);

            $version = WorkflowVersion::create([
                'tenant_id' => $tenant->id,
                'workflow_id' => $workflow->id,
                'version_number' => 1,
                'status' => 'PUBLISHED',
                'graph' => $selectedPreset['graph'],
                'published_at' => Carbon::now(),
            ]);

            $workflow->update([
                'current_version_id' => $version->id,
            ]);

            return $workflow;
        });
    }

    /**
     * Get available business preset templates (PRD 47, 113-119).
     *
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     graph: array{nodes: array<int, mixed>, edges: array<int, mixed>}
     * }>
     */
    public function getAvailablePresets(): array
    {
        return [
            'standar' => [
                'key' => 'standar',
                'name' => 'Workflow Standar Reservasi',
                'description' => 'Alur umum booking: Booking dibuat -> Cek pembayaran -> Konfirmasi otomatis jika lunas atau pending jika belum bayar.',
                'graph' => [
                    'nodes' => [
                        [
                            'id' => 'node_trigger',
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
                            'id' => 'node_cond_payment',
                            'type' => 'condition',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Cek Status Pembayaran',
                                'category' => 'condition',
                                'type' => 'payment_status',
                                'config' => ['payment_status' => 'PAID'],
                            ],
                        ],
                        [
                            'id' => 'node_act_confirm',
                            'type' => 'action',
                            'position' => ['x' => 100, 'y' => 320],
                            'data' => [
                                'label' => 'Ubah Status: Terkonfirmasi',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                        [
                            'id' => 'node_act_notif_confirm',
                            'type' => 'action',
                            'position' => ['x' => 100, 'y' => 450],
                            'data' => [
                                'label' => 'Kirim WA Konfirmasi Reservasi',
                                'category' => 'action',
                                'type' => 'send_notification',
                                'config' => ['channel' => 'whatsapp', 'template' => 'booking_confirmed'],
                            ],
                        ],
                        [
                            'id' => 'node_act_pending',
                            'type' => 'action',
                            'position' => ['x' => 400, 'y' => 320],
                            'data' => [
                                'label' => 'Ubah Status: Menunggu Pembayaran',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'PENDING'],
                            ],
                        ],
                    ],
                    'edges' => [
                        [
                            'id' => 'e1',
                            'source' => 'node_trigger',
                            'target' => 'node_cond_payment',
                            'type' => 'smoothstep',
                        ],
                        [
                            'id' => 'e2',
                            'source' => 'node_cond_payment',
                            'sourceHandle' => 'yes',
                            'target' => 'node_act_confirm',
                            'type' => 'smoothstep',
                        ],
                        [
                            'id' => 'e3',
                            'source' => 'node_act_confirm',
                            'target' => 'node_act_notif_confirm',
                            'type' => 'smoothstep',
                        ],
                        [
                            'id' => 'e4',
                            'source' => 'node_cond_payment',
                            'sourceHandle' => 'no',
                            'target' => 'node_act_pending',
                            'type' => 'smoothstep',
                        ],
                    ],
                ],
            ],
            'salon' => [
                'key' => 'salon',
                'name' => 'Workflow Salon & Barbershop',
                'description' => 'Konfirmasi otomatis reservasi terapis, pengingat H-1 lewat WhatsApp, dan transisi ke selesai.',
                'graph' => [
                    'nodes' => [
                        [
                            'id' => 'node_trigger',
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
                            'id' => 'node_act_confirm',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Konfirmasi Langsung',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'CONFIRMED'],
                            ],
                        ],
                        [
                            'id' => 'node_delay_reminder',
                            'type' => 'delay',
                            'position' => ['x' => 250, 'y' => 310],
                            'data' => [
                                'label' => 'Tunggu Hingga H-1 Jam',
                                'category' => 'delay',
                                'type' => 'delay',
                                'config' => ['duration' => 24, 'unit' => 'hours'],
                            ],
                        ],
                        [
                            'id' => 'node_act_notif',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 440],
                            'data' => [
                                'label' => 'Kirim WA Reminder Jadwal',
                                'category' => 'action',
                                'type' => 'send_notification',
                                'config' => ['channel' => 'whatsapp', 'template' => 'reminder_h1'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'node_trigger', 'target' => 'node_act_confirm', 'type' => 'smoothstep'],
                        ['id' => 'e2', 'source' => 'node_act_confirm', 'target' => 'node_delay_reminder', 'type' => 'smoothstep'],
                        ['id' => 'e3', 'source' => 'node_delay_reminder', 'target' => 'node_act_notif', 'type' => 'smoothstep'],
                    ],
                ],
            ],
            'rental' => [
                'key' => 'rental',
                'name' => 'Workflow Rental Kendaraan / Alat',
                'description' => 'Validasi deposit awal, konfirmasi penyerahan unit, dan penyelesaian sewa saat unit kembali.',
                'graph' => [
                    'nodes' => [
                        [
                            'id' => 'node_trigger',
                            'type' => 'trigger',
                            'position' => ['x' => 250, 'y' => 50],
                            'data' => [
                                'label' => 'Booking Rental Masuk',
                                'category' => 'trigger',
                                'type' => 'booking_created',
                                'config' => [],
                            ],
                        ],
                        [
                            'id' => 'node_act_pending',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 180],
                            'data' => [
                                'label' => 'Tandai Menunggu Verifikasi & DP',
                                'category' => 'action',
                                'type' => 'change_status',
                                'config' => ['target_status' => 'PENDING'],
                            ],
                        ],
                        [
                            'id' => 'node_act_notif',
                            'type' => 'action',
                            'position' => ['x' => 250, 'y' => 310],
                            'data' => [
                                'label' => 'Kirim Instruksi Pengambilan Unit',
                                'category' => 'action',
                                'type' => 'send_notification',
                                'config' => ['channel' => 'whatsapp', 'template' => 'rental_pickup_instructions'],
                            ],
                        ],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'node_trigger', 'target' => 'node_act_pending', 'type' => 'smoothstep'],
                        ['id' => 'e2', 'source' => 'node_act_pending', 'target' => 'node_act_notif', 'type' => 'smoothstep'],
                    ],
                ],
            ],
        ];
    }
}
