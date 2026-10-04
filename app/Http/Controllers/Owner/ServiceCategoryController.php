<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Service\Models\ServiceCategory;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = Str::slug($validated['name']);
        $existing = ServiceCategory::where('business_id', $business->id)->where('slug', $slug)->exists();
        if ($existing) {
            $slug .= '-'.(ServiceCategory::where('business_id', $business->id)->count() + 1);
        }

        $category = ServiceCategory::create([
            'tenant_id' => $tenant->id,
            'business_id' => $business->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'order' => $validated['order'] ?? 0,
        ]);

        Audit::record([
            'action' => 'service_category.created',
            'entity_type' => 'ServiceCategory',
            'entity_id' => $category->id,
            'before' => null,
            'after' => $category->toArray(),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Kategori layanan berhasil ditambahkan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var ServiceCategory $category */
        $category = ServiceCategory::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $beforeState = $category->toArray();
        $category->update($validated);

        Audit::record([
            'action' => 'service_category.updated',
            'entity_type' => 'ServiceCategory',
            'entity_id' => $category->id,
            'before' => $beforeState,
            'after' => $category->toArray(),
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Kategori layanan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        /** @var ServiceCategory $category */
        $category = ServiceCategory::where('tenant_id', $tenant->id)
            ->where('business_id', $business->id)
            ->where('id', $id)
            ->firstOrFail();

        $beforeState = $category->toArray();
        $category->delete();

        Audit::record([
            'action' => 'service_category.deleted',
            'entity_type' => 'ServiceCategory',
            'entity_id' => $category->id,
            'before' => $beforeState,
            'after' => null,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->back()->with('success', 'Kategori layanan berhasil dihapus.');
    }
}
