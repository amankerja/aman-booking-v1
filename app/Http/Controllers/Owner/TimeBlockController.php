<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\TimeBlock;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeBlockController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $resourceId = $request->input('resource_id');

        $query = TimeBlock::where('tenant_id', $tenant->id)
            ->with(['resource.resourceType'])
            ->orderBy('start_at', 'desc');

        if ($resourceId) {
            $query->where(function ($q) use ($resourceId) {
                $q->where('resource_id', $resourceId)
                    ->orWhere('is_all_resources', true);
            });
        }

        $timeBlocks = $query->paginate(20)->through(function (TimeBlock $tb) {
            return [
                'id' => $tb->id,
                'resource_id' => $tb->resource_id,
                'resource_name' => $tb->is_all_resources ? 'Semua Resource' : ($tb->resource ? $tb->resource->name : 'Semua Resource'),
                'resource_code' => $tb->resource ? $tb->resource->code : null,
                'start_at' => $tb->start_at->toIso8601String(),
                'end_at' => $tb->end_at->toIso8601String(),
                'start_formatted' => $tb->start_at->translatedFormat('d M Y H:i'),
                'end_formatted' => $tb->end_at->translatedFormat('d M Y H:i'),
                'reason' => $tb->reason,
                'is_all_resources' => $tb->is_all_resources,
                'created_at' => $tb->created_at?->toIso8601String(),
            ];
        });

        $resources = Resource::where('tenant_id', $tenant->id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Owner/Resources/TimeBlocks', [
            'time_blocks' => $timeBlocks,
            'resources' => $resources,
            'filters' => [
                'resource_id' => $resourceId ? (int) $resourceId : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'is_all_resources' => ['required', 'boolean'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $isAll = (bool) ($validated['is_all_resources'] || empty($validated['resource_id']));

        $block = TimeBlock::create([
            'tenant_id' => $tenant->id,
            'resource_id' => $isAll ? null : $validated['resource_id'],
            'start_at' => Carbon::parse($validated['start_at'])->setTimezone('UTC'),
            'end_at' => Carbon::parse($validated['end_at'])->setTimezone('UTC'),
            'reason' => trim($validated['reason']),
            'is_all_resources' => $isAll,
        ]);

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'time_block.created',
            'entity_type' => 'TimeBlock',
            'entity_id' => $block->id,
            'after' => [
                'resource_id' => $block->resource_id,
                'is_all_resources' => $block->is_all_resources,
                'start_at' => $block->start_at->toIso8601String(),
                'end_at' => $block->end_at->toIso8601String(),
                'reason' => $block->reason,
            ],
        ]);

        return back()->with('success', 'Block waktu / cuti berhasil ditambahkan.');
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $block = TimeBlock::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $beforeState = [
            'resource_id' => $block->resource_id,
            'start_at' => $block->start_at->toIso8601String(),
            'end_at' => $block->end_at->toIso8601String(),
            'reason' => $block->reason,
        ];

        $block->delete();

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'time_block.deleted',
            'entity_type' => 'TimeBlock',
            'entity_id' => $id,
            'before' => $beforeState,
        ]);

        return back()->with('success', 'Block waktu / cuti berhasil dihapus.');
    }
}
