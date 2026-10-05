<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowVersion;
use App\Domain\Workflow\Services\WorkflowService;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowService $workflowService
    ) {}

    /**
     * Display listing of workflows for the tenant (PRD 21, 66).
     */
    public function index(Request $request): Response|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $workflows = Workflow::where('tenant_id', $tenant->id)
            ->with([
                'service:id,name',
                'currentVersion',
                'draftVersion',
            ])
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // If no workflows exist yet for this tenant, auto-seed standard workflow preset
        if ($workflows->isEmpty()) {
            $this->workflowService->seedDefaultWorkflowForTenant($tenant, 'standar');
            $workflows = Workflow::where('tenant_id', $tenant->id)
                ->with([
                    'service:id,name',
                    'currentVersion',
                    'draftVersion',
                ])
                ->get();
        }

        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $presets = array_values($this->workflowService->getAvailablePresets());

        if ($request->wantsJson()) {
            return response()->json([
                'workflows' => $workflows,
                'services' => $services,
                'presets' => $presets,
            ]);
        }

        return Inertia::render('Owner/Workflows/Index', [
            'workflows' => $workflows,
            'services' => $services,
            'presets' => $presets,
        ]);
    }

    /**
     * Display Visual Canvas Workflow Builder (PRD 21, 66).
     */
    public function builder(int $id): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)
            ->with([
                'service:id,name',
                'currentVersion',
                'draftVersion',
                'versions' => fn ($q) => $q->orderBy('version_number', 'desc'),
            ])
            ->findOrFail($id);

        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = BookingStatus::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        $presets = array_values($this->workflowService->getAvailablePresets());

        return Inertia::render('Owner/Workflows/Builder', [
            'workflow' => $workflow,
            'services' => $services,
            'statuses' => $statuses,
            'presets' => $presets,
        ]);
    }

    /**
     * Create a new workflow.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            Workflow::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $workflow = Workflow::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'service_id' => $validated['service_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        // Seed initial empty graph or standard trigger
        $initialGraph = [
            'nodes' => [
                [
                    'id' => 'trigger_1',
                    'type' => 'trigger',
                    'position' => ['x' => 250, 'y' => 50],
                    'data' => [
                        'label' => 'Booking Dibuat',
                        'category' => 'trigger',
                        'type' => 'booking_created',
                        'config' => [],
                    ],
                ],
            ],
            'edges' => [],
        ];

        $draft = $this->workflowService->saveDraft($workflow, $initialGraph, $request->user()?->id);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Alur kerja berhasil dibuat.',
                'workflow' => $workflow->load('currentVersion', 'draftVersion'),
            ], 201);
        }

        return redirect()->route('owner.workflows.builder', ['id' => $workflow->id])
            ->with('success', 'Alur kerja berhasil dibuat. Silakan susun alur pada kanvas.');
    }

    /**
     * Update workflow metadata.
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['is_default']) && ! $workflow->is_default) {
            Workflow::where('tenant_id', $tenant->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $workflow->update([
            'name' => $validated['name'],
            'service_id' => $validated['service_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => (bool) ($validated['is_default'] ?? $workflow->is_default),
            'is_active' => (bool) ($validated['is_active'] ?? $workflow->is_active),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Alur kerja berhasil diperbarui.',
                'workflow' => $workflow,
            ]);
        }

        return redirect()->back()->with('success', 'Pengaturan alur kerja berhasil disimpan.');
    }

    /**
     * Deactivate / delete workflow.
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($workflow->is_default) {
            return redirect()->back()->withErrors(['error' => 'Alur kerja utama (default) tidak dapat dihapus. Jadikan alur kerja lain sebagai default terlebih dahulu.']);
        }

        $workflow->delete();

        return redirect()->route('owner.workflows.index')->with('success', 'Alur kerja berhasil dihapus.');
    }

    /**
     * Save draft graph from XYFlow editor (PRD 66, autosave).
     */
    public function saveDraft(Request $request, int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'graph' => ['required', 'array'],
            'graph.nodes' => ['present', 'array'],
            'graph.edges' => ['present', 'array'],
        ]);

        $draft = $this->workflowService->saveDraft($workflow, $validated['graph'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Draf alur kerja berhasil disimpan.',
            'version' => $draft,
        ]);
    }

    /**
     * Publish draft graph to immutable published version (PRD 23, 204.5).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)->findOrFail($id);

        $versionId = $request->input('version_id');

        try {
            $published = $this->workflowService->publishVersion(
                $workflow,
                $versionId ? (int) $versionId : null,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Versi {$published->version_number} berhasil dipublikasikan dan aktif.",
                'version' => $published,
                'workflow' => $workflow->fresh(['currentVersion', 'draftVersion']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Test dry run simulation of graph (PRD 66).
     */
    public function testRun(Request $request, int $id): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Workflow $workflow */
        $workflow = Workflow::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'graph' => ['nullable', 'array'],
            'graph.nodes' => ['nullable', 'array'],
            'graph.edges' => ['nullable', 'array'],
            'mock_payload' => ['nullable', 'array'],
        ]);

        $graph = $validated['graph'] ?? null;
        if (! $graph || empty($graph['nodes'])) {
            $workflow->loadMissing('draftVersion', 'currentVersion');
            $draftVersion = $workflow->draftVersion;
            $currentVersion = $workflow->currentVersion;
            $graph = $draftVersion !== null ? $draftVersion->graph : ($currentVersion !== null ? $currentVersion->graph : null);
        }

        if (! is_array($graph) || empty($graph['nodes'])) {
            return response()->json([
                'success' => false,
                'message' => 'Graf alur kerja kosong atau tidak valid.',
            ], 422);
        }

        /** @var array{nodes: array<int, mixed>, edges: array<int, mixed>} $graphArray */
        $graphArray = $graph;

        $mockPayload = $validated['mock_payload'] ?? [
            'status' => 'PENDING',
            'payment_status' => 'PAID',
            'total_idr' => 150000,
        ];

        $result = $this->workflowService->testDryRun($workflow, $graphArray, $mockPayload);

        return response()->json($result);
    }

    /**
     * Install preset business workflow template (PRD 47).
     */
    public function installPreset(Request $request, string $preset): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $workflow = $this->workflowService->seedDefaultWorkflowForTenant($tenant, $preset);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Template '{$workflow->name}' berhasil diinstal.",
                'workflow' => $workflow->load('currentVersion'),
            ]);
        }

        return redirect()->route('owner.workflows.builder', ['id' => $workflow->id])
            ->with('success', "Template '{$workflow->name}' berhasil diinstal.");
    }
}
