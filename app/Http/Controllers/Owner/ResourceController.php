<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\BusinessHour;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceGroup;
use App\Domain\Resource\Models\ResourceSchedule;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Subscription\Exceptions\PlanLimitReachedException;
use App\Domain\Subscription\Services\LimitEnforcer;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(
        protected LimitEnforcer $limitEnforcer
    ) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $search = $request->string('search')->trim()->value();
        $typeId = $request->input('type_id');
        $state = $request->string('state', 'ALL')->value();
        $status = $request->string('status', 'ACTIVE')->value(); // ACTIVE, ARCHIVED, ALL

        $query = Resource::where('business_id', $business->id)
            ->with(['resourceType', 'group'])
            ->withCount(['schedules', 'timeBlocks'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($typeId) {
            $query->where('resource_type_id', $typeId);
        }

        if ($state !== 'ALL') {
            $query->where('state', $state);
        }

        if ($status === 'ACTIVE') {
            $query->whereNull('archived_at');
        } elseif ($status === 'ARCHIVED') {
            $query->whereNotNull('archived_at');
        }

        $resources = $query->get()->map(function (Resource $r) {
            return [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'name' => $r->name,
                'code' => $r->code,
                'capacity' => $r->capacity,
                'visibility' => $r->visibility,
                'state' => $r->state,
                'skills' => $r->skills ?? [],
                'metadata' => $r->metadata,
                'archived_at' => $r->archived_at?->toIso8601String(),
                'is_archived' => $r->is_archived,
                'resource_type' => $r->resourceType ? [
                    'id' => $r->resourceType->id,
                    'code' => $r->resourceType->code,
                    'name' => $r->resourceType->name,
                    'icon' => $r->resourceType->icon,
                    'is_staff' => $r->resourceType->is_staff,
                    'is_space' => $r->resourceType->is_space,
                    'is_equipment' => $r->resourceType->is_equipment,
                ] : null,
                'group' => $r->group ? [
                    'id' => $r->group->id,
                    'name' => $r->group->name,
                ] : null,
                'schedules_count' => $r->schedules_count,
                'time_blocks_count' => $r->time_blocks_count,
            ];
        });

        $resourceTypes = ResourceType::forCurrentTenant($tenant->id)->get()->map(fn (ResourceType $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'icon' => $t->icon,
            'is_staff' => $t->is_staff,
            'is_space' => $t->is_space,
            'is_equipment' => $t->is_equipment,
        ]);

        $resourceGroups = ResourceGroup::where('tenant_id', $tenant->id)->get()->map(fn (ResourceGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'description' => $g->description,
        ]);

        $quota = $this->limitEnforcer->getUsage('resources', $tenant);

        return Inertia::render('Owner/Resources/Index', [
            'resources' => $resources,
            'resource_types' => $resourceTypes,
            'resource_groups' => $resourceGroups,
            'quota' => $quota,
            'filters' => [
                'search' => $search,
                'type_id' => $typeId ? (int) $typeId : null,
                'state' => $state,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $resourceTypes = ResourceType::forCurrentTenant($tenant->id)->get()->map(fn (ResourceType $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'icon' => $t->icon,
            'is_staff' => $t->is_staff,
            'is_space' => $t->is_space,
            'is_equipment' => $t->is_equipment,
        ]);

        $resourceGroups = ResourceGroup::where('tenant_id', $tenant->id)->get()->map(fn (ResourceGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'description' => $g->description,
        ]);

        // Default schedules from business hours if available, otherwise 09:00 - 17:00
        $businessHours = BusinessHour::where('business_id', $business->id)->get()->keyBy('day_of_week');
        $defaultSchedules = [];

        for ($day = 0; $day <= 6; $day++) {
            $bh = $businessHours->get($day);
            $defaultSchedules[] = [
                'day_of_week' => $day,
                'is_available' => $bh ? (bool) $bh->is_open : ($day > 0), // Default Sunday closed
                'start_time' => $bh ? substr($bh->open_time, 0, 5) : '09:00',
                'end_time' => $bh ? substr($bh->close_time, 0, 5) : '17:00',
                'breaks' => $bh && ! empty($bh->breaks) ? $bh->breaks : [],
            ];
        }

        $quota = $this->limitEnforcer->getUsage('resources', $tenant);

        return Inertia::render('Owner/Resources/Form', [
            'resource' => null,
            'resource_types' => $resourceTypes,
            'resource_groups' => $resourceGroups,
            'default_schedules' => $defaultSchedules,
            'quota' => $quota,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        // Enforce subscription plan limit for resources
        try {
            $this->limitEnforcer->enforce('resources', $tenant);
        } catch (PlanLimitReachedException $e) {
            return back()->with('error', $e->getMessage());
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'resource_type_id' => ['required', 'integer', 'exists:resource_types,id'],
            'group_id' => ['nullable', 'integer', 'exists:resource_groups,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'visibility' => ['required', 'string', 'in:PUBLIC,INTERNAL'],
            'state' => ['required', 'string', 'in:AVAILABLE,BLOCKED,MAINTENANCE,INACTIVE'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.is_available' => ['required', 'boolean'],
            'schedules.*.start_time' => ['required', 'string'],
            'schedules.*.end_time' => ['required', 'string'],
            'schedules.*.breaks' => ['nullable', 'array'],
            'schedules.*.breaks.*.start' => ['required', 'string'],
            'schedules.*.breaks.*.end' => ['required', 'string'],
            'schedules.*.breaks.*.title' => ['nullable', 'string'],
        ]);

        $resource = DB::transaction(function () use ($validated, $tenant, $business) {
            $resource = Resource::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'resource_type_id' => $validated['resource_type_id'],
                'group_id' => $validated['group_id'] ?? null,
                'uuid' => (string) Str::uuid(),
                'name' => $validated['name'],
                'code' => ! empty($validated['code']) ? trim($validated['code']) : null,
                'capacity' => $validated['capacity'] ?? 1,
                'visibility' => $validated['visibility'] ?? 'PUBLIC',
                'state' => $validated['state'] ?? 'AVAILABLE',
                'skills' => ! empty($validated['skills']) ? array_values(array_filter($validated['skills'])) : null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Save weekly schedules
            if (! empty($validated['schedules'])) {
                foreach ($validated['schedules'] as $sch) {
                    ResourceSchedule::create([
                        'tenant_id' => $tenant->id,
                        'resource_id' => $resource->id,
                        'day_of_week' => $sch['day_of_week'],
                        'is_available' => $sch['is_available'],
                        'start_time' => $sch['start_time'],
                        'end_time' => $sch['end_time'],
                        'breaks' => ! empty($sch['breaks']) ? array_values($sch['breaks']) : null,
                    ]);
                }
            } else {
                // Default 7 days
                for ($day = 0; $day <= 6; $day++) {
                    ResourceSchedule::create([
                        'tenant_id' => $tenant->id,
                        'resource_id' => $resource->id,
                        'day_of_week' => $day,
                        'is_available' => $day > 0,
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                        'breaks' => null,
                    ]);
                }
            }

            Audit::record([
                'tenant_id' => $tenant->id,
                'action' => 'resource.created',
                'entity_type' => 'Resource',
                'entity_id' => $resource->id,
                'after' => [
                    'name' => $resource->name,
                    'code' => $resource->code,
                    'type_id' => $resource->resource_type_id,
                    'capacity' => $resource->capacity,
                    'state' => $resource->state,
                ],
            ]);

            return $resource;
        });

        return redirect()->route('owner.resources.index')->with('success', "Resource '{$resource->name}' berhasil ditambahkan.");
    }

    public function edit(int $id): Response
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var resource $resource */
        $resource = Resource::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->with(['resourceType', 'group', 'schedules', 'timeBlocks' => fn ($q) => $q->orderBy('start_at')])
            ->firstOrFail();

        $resourceTypes = ResourceType::forCurrentTenant($tenant->id)->get()->map(fn (ResourceType $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'icon' => $t->icon,
            'is_staff' => $t->is_staff,
            'is_space' => $t->is_space,
            'is_equipment' => $t->is_equipment,
        ]);

        $resourceGroups = ResourceGroup::where('tenant_id', $tenant->id)->get()->map(fn (ResourceGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'description' => $g->description,
        ]);

        // Ensure all 7 days exist in schedules
        $existingSchedules = $resource->schedules->keyBy('day_of_week');
        $schedules = [];

        for ($day = 0; $day <= 6; $day++) {
            if ($existingSchedules->has($day)) {
                $sch = $existingSchedules->get($day);
                $schedules[] = [
                    'day_of_week' => $day,
                    'is_available' => (bool) $sch->is_available,
                    'start_time' => substr($sch->start_time, 0, 5),
                    'end_time' => substr($sch->end_time, 0, 5),
                    'breaks' => $sch->breaks ?? [],
                ];
            } else {
                $schedules[] = [
                    'day_of_week' => $day,
                    'is_available' => $day > 0,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'breaks' => [],
                ];
            }
        }

        $quota = $this->limitEnforcer->getUsage('resources', $tenant);

        return Inertia::render('Owner/Resources/Form', [
            'resource' => [
                'id' => $resource->id,
                'uuid' => $resource->uuid,
                'name' => $resource->name,
                'code' => $resource->code,
                'resource_type_id' => $resource->resource_type_id,
                'group_id' => $resource->group_id,
                'capacity' => $resource->capacity,
                'visibility' => $resource->visibility,
                'state' => $resource->state,
                'skills' => $resource->skills ?? [],
                'metadata' => $resource->metadata,
                'is_archived' => $resource->is_archived,
                'archived_at' => $resource->archived_at?->toIso8601String(),
                'schedules' => $schedules,
                'time_blocks' => $resource->timeBlocks->map(fn ($tb) => [
                    'id' => $tb->id,
                    'start_at' => $tb->start_at->toIso8601String(),
                    'end_at' => $tb->end_at->toIso8601String(),
                    'reason' => $tb->reason,
                    'is_all_resources' => $tb->is_all_resources,
                ]),
            ],
            'resource_types' => $resourceTypes,
            'resource_groups' => $resourceGroups,
            'quota' => $quota,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var resource $resource */
        $resource = Resource::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'resource_type_id' => ['required', 'integer', 'exists:resource_types,id'],
            'group_id' => ['nullable', 'integer', 'exists:resource_groups,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'visibility' => ['required', 'string', 'in:PUBLIC,INTERNAL'],
            'state' => ['required', 'string', 'in:AVAILABLE,BLOCKED,MAINTENANCE,INACTIVE'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.is_available' => ['required', 'boolean'],
            'schedules.*.start_time' => ['required', 'string'],
            'schedules.*.end_time' => ['required', 'string'],
            'schedules.*.breaks' => ['nullable', 'array'],
            'schedules.*.breaks.*.start' => ['required', 'string'],
            'schedules.*.breaks.*.end' => ['required', 'string'],
            'schedules.*.breaks.*.title' => ['nullable', 'string'],
        ]);

        $beforeState = [
            'name' => $resource->name,
            'code' => $resource->code,
            'type_id' => $resource->resource_type_id,
            'capacity' => $resource->capacity,
            'state' => $resource->state,
            'skills' => $resource->skills,
        ];

        DB::transaction(function () use ($resource, $validated, $tenant, $beforeState) {
            $resource->update([
                'resource_type_id' => $validated['resource_type_id'],
                'group_id' => $validated['group_id'] ?? null,
                'name' => $validated['name'],
                'code' => ! empty($validated['code']) ? trim($validated['code']) : null,
                'capacity' => $validated['capacity'] ?? 1,
                'visibility' => $validated['visibility'] ?? 'PUBLIC',
                'state' => $validated['state'] ?? 'AVAILABLE',
                'skills' => ! empty($validated['skills']) ? array_values(array_filter($validated['skills'])) : null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // Sync schedules
            if (! empty($validated['schedules'])) {
                foreach ($validated['schedules'] as $sch) {
                    ResourceSchedule::updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'resource_id' => $resource->id,
                            'day_of_week' => $sch['day_of_week'],
                        ],
                        [
                            'is_available' => $sch['is_available'],
                            'start_time' => $sch['start_time'],
                            'end_time' => $sch['end_time'],
                            'breaks' => ! empty($sch['breaks']) ? array_values($sch['breaks']) : null,
                        ]
                    );
                }
            }

            Audit::record([
                'tenant_id' => $tenant->id,
                'action' => 'resource.updated',
                'entity_type' => 'Resource',
                'entity_id' => $resource->id,
                'before' => $beforeState,
                'after' => [
                    'name' => $resource->name,
                    'code' => $resource->code,
                    'type_id' => $resource->resource_type_id,
                    'capacity' => $resource->capacity,
                    'state' => $resource->state,
                    'skills' => $resource->skills,
                ],
            ]);
        });

        return redirect()->route('owner.resources.index')->with('success', "Resource '{$resource->name}' berhasil diperbarui.");
    }

    public function archive(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var resource $resource */
        $resource = Resource::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $resource->update(['archived_at' => now()]);

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'resource.archived',
            'entity_type' => 'Resource',
            'entity_id' => $resource->id,
            'after' => ['archived_at' => now()->toIso8601String()],
        ]);

        return back()->with('success', "Resource '{$resource->name}' berhasil diarsipkan.");
    }

    public function unarchive(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var resource $resource */
        $resource = Resource::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        try {
            $this->limitEnforcer->enforce('resources', $tenant);
        } catch (PlanLimitReachedException $e) {
            return back()->with('error', $e->getMessage());
        }

        $resource->update(['archived_at' => null]);

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'resource.unarchived',
            'entity_type' => 'Resource',
            'entity_id' => $resource->id,
            'after' => ['archived_at' => null],
        ]);

        return back()->with('success', "Resource '{$resource->name}' berhasil diaktifkan kembali.");
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var resource $resource */
        $resource = Resource::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        // Check if resource is booked (booking_allocations table check when exists)
        $hasAllocations = Schema::hasTable('booking_allocations') && DB::table('booking_allocations')
            ->where('resource_id', $resource->id)
            ->exists();

        if ($hasAllocations) {
            $resource->update(['archived_at' => now()]);

            return back()->with('success', 'Resource memiliki riwayat booking sehingga diarsipkan (bukan dihapus permanen).');
        }

        $resourceName = $resource->name;
        $resource->delete();

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'resource.deleted',
            'entity_type' => 'Resource',
            'entity_id' => $id,
            'before' => ['name' => $resourceName],
        ]);

        return redirect()->route('owner.resources.index')->with('success', "Resource '{$resourceName}' berhasil dihapus.");
    }
}
