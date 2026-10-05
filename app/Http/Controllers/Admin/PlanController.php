<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Identity\Models\User;
use App\Domain\Subscription\Models\Plan;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Display a listing of subscription plans with usage metrics (PRD 75).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        $plans = Plan::withCount(['subscriptions' => function ($q) {
            $q->whereIn('status', ['ACTIVE', 'TRIAL', 'GRACE_PERIOD']);
        }])->get();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Update the specified plan parameters (PRD 75).
     * Modifying plan limits and features preserves existing subscriptions.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        /** @var Plan $plan */
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price_idr' => ['required', 'integer', 'min:0'],
            'billing_cycle' => ['required', 'string', 'in:MONTHLY,ANNUAL'],
            'limits' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
            'is_active' => ['required', 'boolean'],
        ]);

        $beforeState = $plan->toArray();

        $plan->name = (string) $validated['name'];
        $plan->price_idr = (int) $validated['price_idr'];
        $plan->billing_cycle = (string) $validated['billing_cycle'];
        if (isset($validated['limits'])) {
            $plan->limits = $validated['limits'];
        }
        if (isset($validated['features'])) {
            $plan->features = $validated['features'];
        }
        $plan->is_active = (bool) $validated['is_active'];
        $plan->save();

        Audit::record([
            'tenant_id' => null,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.plan_updated',
            'entity_type' => 'plan',
            'entity_id' => $plan->id,
            'before' => $beforeState,
            'after' => $plan->toArray(),
            'source' => 'web',
        ]);

        return back()->with('success', "Paket '{$plan->name}' berhasil diperbarui tanpa mengganggu data existing (PRD 75).");
    }
}
