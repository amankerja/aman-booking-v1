<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Exceptions\BookingException;
use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Booking\Services\BookingStatusService;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingStatusController extends Controller
{
    public function __construct(
        protected BookingStatusService $statusService
    ) {}

    /**
     * Display custom statuses and configuration (PRD 24, 25, 140, 213).
     */
    public function index(Request $request): Response|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $statuses = $this->statusService->getStatusesForTenant($tenant);

        if ($request->wantsJson()) {
            return response()->json([
                'statuses' => $statuses,
            ]);
        }

        $systemCategories = array_map(fn (BookingStatusCategory $c) => [
            'value' => $c->value,
            'label' => $c->value,
        ], BookingStatusCategory::cases());

        return Inertia::render('Owner/Settings/Statuses', [
            'statuses' => $statuses,
            'systemCategories' => $systemCategories,
        ]);
    }

    /**
     * Store a newly created custom status.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'category' => ['required', 'string', 'in:DRAFT,PENDING,CONFIRMED,CHECKED_IN,IN_PROGRESS,COMPLETED,CANCELLED,NO_SHOW,EXPIRED'],
            'color' => ['nullable', 'string', 'max:32'],
            'badge_bg' => ['nullable', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $status = $this->statusService->createStatus($tenant, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Status kustom '{$status->name}' berhasil dibuat.",
                'status' => $status,
            ], 201);
        }

        return back()->with('success', "Status kustom '{$status->name}' berhasil dibuat.");
    }

    /**
     * Update an existing custom status.
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var BookingStatus $status */
        $status = BookingStatus::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'string', 'in:DRAFT,PENDING,CONFIRMED,CHECKED_IN,IN_PROGRESS,COMPLETED,CANCELLED,NO_SHOW,EXPIRED'],
            'color' => ['nullable', 'string', 'max:32'],
            'badge_bg' => ['nullable', 'string', 'max:64'],
            'icon' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->statusService->updateStatus($status, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Status '{$updated->name}' berhasil diperbarui.",
                'status' => $updated,
            ]);
        }

        return back()->with('success', "Status '{$updated->name}' berhasil diperbarui.");
    }

    /**
     * Delete a custom status.
     */
    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        /** @var BookingStatus $status */
        $status = BookingStatus::where('tenant_id', $tenant->id)->findOrFail($id);

        try {
            $this->statusService->deleteStatus($status);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Status '{$status->name}' berhasil dihapus.",
                ]);
            }

            return back()->with('success', "Status '{$status->name}' berhasil dihapus.");
        } catch (BookingException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    /**
     * Reorder statuses for Kanban and lists.
     */
    public function reorder(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        $this->statusService->reorderStatuses($tenant, $validated['ordered_ids']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Urutan status berhasil diperbarui.',
            ]);
        }

        return back()->with('success', 'Urutan status berhasil diperbarui.');
    }

    /**
     * Reset and seed default statuses.
     */
    public function seedDefaults(Request $request): RedirectResponse|JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = TenantContext::getTenant();

        $statuses = $this->statusService->seedDefaultStatuses($tenant);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Status default berhasil dipasang.',
                'statuses' => $statuses,
            ]);
        }

        return back()->with('success', 'Status default berhasil dipasang.');
    }
}
