<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Resource\Models\ResourceGroup;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ResourceGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $group = ResourceGroup::create([
            'tenant_id' => $tenant->id,
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']),
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
        ]);

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'resource_group.created',
            'entity_type' => 'ResourceGroup',
            'entity_id' => $group->id,
            'after' => ['name' => $group->name],
        ]);

        return back()->with('success', "Grup resource '{$group->name}' berhasil dibuat.");
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $group = ResourceGroup::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        $groupName = $group->name;
        $group->delete();

        Audit::record([
            'tenant_id' => $tenant->id,
            'action' => 'resource_group.deleted',
            'entity_type' => 'ResourceGroup',
            'entity_id' => $id,
            'before' => ['name' => $groupName],
        ]);

        return back()->with('success', "Grup resource '{$groupName}' berhasil dihapus.");
    }
}
