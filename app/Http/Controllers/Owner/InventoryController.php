<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\StockDeductionMode;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\ServiceInventoryItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Check staff permission for inventory actions (PRD 212).
     */
    protected function authorizeInventoryAction(string $permission = 'inventory.view'): void
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if ($user->id === $tenant->owner_user_id || (bool) $user->is_super_admin) {
            return;
        }

        /** @var BusinessMember|null $member */
        $member = BusinessMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        $memberPerms = $member !== null && is_array($member->permissions) ? $member->permissions : [];

        // Full wildcard
        if (in_array('*', $memberPerms, true) || in_array('inventory.*', $memberPerms, true)) {
            return;
        }

        // Action specific
        if (in_array($permission, $memberPerms, true)) {
            return;
        }

        // Preset fallback
        $preset = $member?->preset;
        if (in_array($preset, ['owner', 'manager'], true)) {
            return;
        }

        if ($permission === 'inventory.view' && in_array($preset, ['front_desk', 'staff', 'viewer'], true)) {
            return;
        }

        if ($permission === 'inventory.adjust' && $preset === 'front_desk') {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk tindakan inventori ini.');
    }

    /**
     * Display inventory dashboard & items list (PRD 17, 214).
     */
    public function index(Request $request): Response
    {
        $this->authorizeInventoryAction('inventory.view');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $isModuleActive = $this->inventoryService->isModuleActive($tenant);

        $search = $request->string('search')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $lowStockOnly = $request->boolean('low_stock');

        $query = InventoryItem::where('tenant_id', $tenant->id)
            ->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($lowStockOnly) {
            $query->where('minimum_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock');
        }

        $items = $query->paginate(20)->withQueryString();

        // Calculate KPI stats
        $allItems = InventoryItem::where('tenant_id', $tenant->id)->get();
        $totalItems = $allItems->count();
        $totalValuation = $allItems->sum(fn ($i) => $i->current_stock * $i->cost_price_idr);
        $lowStockCount = $allItems->filter(fn ($i) => $i->is_low_stock)->count();
        $movementsThisMonth = InventoryMovement::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // Available categories for filter
        $categories = InventoryItem::where('tenant_id', $tenant->id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->values();

        // Services for mapping modal
        $services = Service::where('tenant_id', $tenant->id)
            ->with(['inventoryItems.inventoryItem'])
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Owner/Inventory/Index', [
            'is_module_active' => $isModuleActive,
            'items' => $items,
            'stats' => [
                'total_items' => $totalItems,
                'total_valuation_idr' => $totalValuation,
                'low_stock_count' => $lowStockCount,
                'movements_this_month' => $movementsThisMonth,
            ],
            'categories' => $categories,
            'services' => $services,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'low_stock' => $lowStockOnly,
            ],
            'movement_types' => collect(MovementType::cases())
                ->filter(fn ($t) => $t !== MovementType::CONSUMED_BY_BOOKING)
                ->map(fn ($t) => [
                    'value' => $t->value,
                    'label' => $t->label(),
                    'multiplier' => $t->multiplier(),
                ])
                ->values(),
            'stock_modes' => collect(StockDeductionMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
        ]);
    }

    /**
     * Create a new inventory item.
     */
    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.manage');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $validated = $request->validate([
            'sku' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:30',
            'cost_price_idr' => 'required|integer|min:0',
            'sale_price_idr' => 'nullable|integer|min:0',
            'initial_stock' => 'required|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'allow_negative_stock' => 'boolean',
        ]);

        if (! empty($validated['sku'])) {
            $exists = InventoryItem::where('tenant_id', $tenant->id)
                ->where('sku', $validated['sku'])
                ->exists();
            if ($exists) {
                return back()->with('error', "SKU '{$validated['sku']}' sudah digunakan untuk item lain.");
            }
        }

        DB::transaction(function () use ($tenant, $business, $validated) {
            $initialStock = (int) $validated['initial_stock'];

            /** @var InventoryItem $item */
            $item = InventoryItem::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'sku' => $validated['sku'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'unit' => $validated['unit'],
                'cost_price_idr' => $validated['cost_price_idr'],
                'sale_price_idr' => $validated['sale_price_idr'] ?? 0,
                'initial_stock' => $initialStock,
                'current_stock' => $initialStock,
                'reserved_stock' => 0,
                'minimum_stock' => $validated['minimum_stock'] ?? 0,
                'allow_negative_stock' => $validated['allow_negative_stock'] ?? false,
                'is_active' => true,
            ]);

            if ($initialStock > 0) {
                InventoryMovement::create([
                    'tenant_id' => $tenant->id,
                    'inventory_item_id' => $item->id,
                    'type' => MovementType::OPENING,
                    'quantity' => $initialStock,
                    'stock_before' => 0,
                    'stock_after' => $initialStock,
                    'unit_cost_idr' => $item->cost_price_idr,
                    'notes' => 'Stok awal barang',
                    'actor_id' => auth()->id(),
                ]);
            }
        });

        return back()->with('success', "Item '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Update existing inventory item.
     */
    public function updateItem(Request $request, int $id): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.manage');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var InventoryItem $item */
        $item = InventoryItem::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'sku' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:100',
            'unit' => 'required|string|max:30',
            'cost_price_idr' => 'required|integer|min:0',
            'sale_price_idr' => 'nullable|integer|min:0',
            'minimum_stock' => 'nullable|integer|min:0',
            'allow_negative_stock' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['sku']) && $validated['sku'] !== $item->sku) {
            $exists = InventoryItem::where('tenant_id', $tenant->id)
                ->where('sku', $validated['sku'])
                ->where('id', '!=', $item->id)
                ->exists();
            if ($exists) {
                return back()->with('error', "SKU '{$validated['sku']}' sudah digunakan untuk item lain.");
            }
        }

        $item->update([
            'sku' => $validated['sku'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'unit' => $validated['unit'],
            'cost_price_idr' => $validated['cost_price_idr'],
            'sale_price_idr' => $validated['sale_price_idr'] ?? 0,
            'minimum_stock' => $validated['minimum_stock'] ?? 0,
            'allow_negative_stock' => $validated['allow_negative_stock'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', "Item '{$item->name}' berhasil diperbarui.");
    }

    /**
     * Delete / archive an inventory item.
     */
    public function deleteItem(int $id): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.manage');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var InventoryItem $item */
        $item = InventoryItem::where('tenant_id', $tenant->id)->findOrFail($id);

        if ($item->reserved_stock > 0) {
            return back()->with('error', "Item '{$item->name}' memiliki reservasi booking aktif dan tidak dapat dihapus.");
        }

        $itemName = $item->name;
        $item->delete();

        return back()->with('success', "Item '{$itemName}' berhasil dihapus.");
    }

    /**
     * Record a stock mutation manually (PRD 17.2).
     */
    public function recordMovement(Request $request, int $id): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.adjust');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'type' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'unit_cost_idr' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->inventoryService->recordMovement([
                'tenant_id' => $tenant->id,
                'inventory_item_id' => $id,
                'type' => $validated['type'],
                'quantity' => (int) $validated['quantity'],
                'unit_cost_idr' => $validated['unit_cost_idr'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'actor_id' => auth()->id(),
            ]);

            return back()->with('success', 'Mutasi stok berhasil dicatat.');
        } catch (InventoryException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get mutation logs for an inventory item.
     */
    public function getMovements(int $id): JsonResponse
    {
        $this->authorizeInventoryAction('inventory.view');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var InventoryItem $item */
        $item = InventoryItem::where('tenant_id', $tenant->id)->findOrFail($id);

        $movements = InventoryMovement::where('tenant_id', $tenant->id)
            ->where('inventory_item_id', $item->id)
            ->with(['actor:id,name', 'booking:id,code'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'multiplier' => $m->type->multiplier(),
                'quantity' => $m->quantity,
                'stock_before' => $m->stock_before,
                'stock_after' => $m->stock_after,
                'unit_cost_idr' => $m->unit_cost_idr,
                'notes' => $m->notes,
                'actor_name' => $m->actor !== null ? $m->actor->name : 'Sistem',
                'booking_code' => $m->booking !== null ? $m->booking->code : null,
                'created_at' => $m->created_at->format('d M Y H:i'),
            ]);

        return response()->json([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'current_stock' => $item->current_stock,
                'reserved_stock' => $item->reserved_stock,
                'available_stock' => $item->available_stock,
            ],
            'movements' => $movements,
        ]);
    }

    /**
     * Toggle the inventory module on/off for this business.
     */
    public function toggleModule(Request $request): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.manage');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var Business $business */
        $business = Business::where('tenant_id', $tenant->id)->firstOrFail();

        $enable = $request->boolean('enabled');

        $settings = $business->settings ?? [];
        $settings['modules'] = $settings['modules'] ?? [];
        $settings['modules']['inventory'] = $enable;
        $settings['inventory_enabled'] = $enable;

        $business->settings = $settings;
        $business->save();

        $statusText = $enable ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Modul inventori berhasil {$statusText}.");
    }

    /**
     * Update linked inventory items for a service (Service -> Inventory Mapping).
     */
    public function updateServiceMapping(Request $request, int $serviceId): RedirectResponse
    {
        $this->authorizeInventoryAction('inventory.manage');

        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();
        /** @var Service $service */
        $service = Service::where('tenant_id', $tenant->id)->findOrFail($serviceId);

        $validated = $request->validate([
            'mappings' => 'array',
            'mappings.*.inventory_item_id' => 'required|integer',
            'mappings.*.quantity' => 'required|integer|min:1',
            'mappings.*.deduction_mode' => 'nullable|string',
        ]);

        DB::transaction(function () use ($tenant, $service, $validated) {
            ServiceInventoryItem::where('tenant_id', $tenant->id)
                ->where('service_id', $service->id)
                ->delete();

            if (! empty($validated['mappings'])) {
                foreach ($validated['mappings'] as $item) {
                    ServiceInventoryItem::create([
                        'tenant_id' => $tenant->id,
                        'service_id' => $service->id,
                        'inventory_item_id' => (int) $item['inventory_item_id'],
                        'quantity' => (int) $item['quantity'],
                        'deduction_mode' => $item['deduction_mode'] ?? null,
                    ]);
                }
            }
        });

        return back()->with('success', "Kebutuhan bahan/stok layanan '{$service->name}' berhasil diperbarui.");
    }
}
