<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Service\Models\Service;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Display a paginated listing of tenants with search, status & plan filters.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $planId = $request->input('plan_id');

        $query = Tenant::with(['owner', 'business', 'currentSubscription.plan'])
            ->withCount(['members', 'businesses'])
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($userQ) use ($search) {
                        $userQ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('businesses', function ($bizQ) use ($search) {
                        $bizQ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '' && $status !== 'ALL') {
            if (in_array($status, ['ACTIVE', 'SUSPENDED', 'CANCELLED'], true)) {
                $query->where('status', $status);
            } else {
                // Subscription-level status like TRIAL or EXPIRED
                $query->whereHas('currentSubscription', function ($subQ) use ($status) {
                    $subQ->where('status', $status);
                });
            }
        }

        if (! empty($planId) && $planId !== 'ALL') {
            $query->whereHas('currentSubscription', function ($subQ) use ($planId) {
                $subQ->where('plan_id', (int) $planId);
            });
        }

        $tenants = $query->paginate(15)->withQueryString();

        // Attach operational counts for current page
        $tenants->getCollection()->transform(function (Tenant $tenant) {
            $servicesCount = Service::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();

            $bookingsCount = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count();

            $latestBooking = Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->latest('created_at')
                ->first();

            /** @var array<string, mixed> $attributes */
            $attributes = $tenant->toArray();
            $attributes['services_count'] = $servicesCount;
            $attributes['bookings_count'] = $bookingsCount;
            $attributes['last_activity'] = $latestBooking?->created_at?->toIso8601String() ?? $tenant->created_at->toIso8601String();

            return $attributes;
        });

        $plans = Plan::where('is_active', true)->get(['id', 'code', 'name']);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'ALL',
                'plan_id' => $planId ?: 'ALL',
            ],
            'plans' => $plans,
        ]);
    }

    /**
     * Display the detailed tenant profile with overview, subscription, usage, invoices, and audit tabs.
     */
    public function show(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        /** @var Tenant $tenant */
        $tenant = Tenant::with([
            'owner',
            'businesses',
            'members.user',
            'subscriptions.plan',
            'currentSubscription.plan',
        ])->findOrFail($id);

        $services = Service::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->get();

        $recentBookings = Booking::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['service', 'customer'])
            ->latest('start_at')
            ->limit(15)
            ->get();

        $invoices = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('payments')
            ->latest()
            ->limit(20)
            ->get();

        $auditLogs = AuditLog::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('actor')
            ->latest()
            ->limit(40)
            ->get();

        $usage = [
            'members_count' => $tenant->members->count(),
            'services_count' => $services->count(),
            'bookings_month_count' => Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'bookings_total_count' => Booking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->count(),
        ];

        $availablePlans = Plan::where('is_active', true)->get();

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'services' => $services,
            'recentBookings' => $recentBookings,
            'invoices' => $invoices,
            'auditLogs' => $auditLogs,
            'usage' => $usage,
            'availablePlans' => $availablePlans,
        ]);
    }

    /**
     * Suspend a tenant and its current subscription (PRD 73, 81).
     */
    public function suspend(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);
        $tenant->status = 'SUSPENDED';
        $tenant->save();

        /** @var Subscription|null $sub */
        $sub = $tenant->currentSubscription()->first();
        if ($sub) {
            $sub->status = 'SUSPENDED';
            $sub->save();
        }

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.tenant_suspended',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'after' => [
                'reason' => $validated['reason'] ?? 'Penonaktifan oleh Super Admin',
            ],
            'source' => 'web',
        ]);

        return back()->with('success', "Tenant '{$tenant->name}' berhasil dinonaktifkan (SUSPENDED).");
    }

    /**
     * Activate a suspended tenant back to ACTIVE status (PRD 73).
     */
    public function activate(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);
        $tenant->status = 'ACTIVE';
        $tenant->save();

        /** @var Subscription|null $sub */
        $sub = $tenant->currentSubscription()->first();
        if ($sub) {
            $sub->status = 'ACTIVE';
            $sub->save();
        }

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.tenant_activated',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'after' => [
                'status' => 'ACTIVE',
            ],
            'source' => 'web',
        ]);

        return back()->with('success', "Tenant '{$tenant->name}' berhasil diaktifkan kembali.");
    }

    /**
     * Change plan of tenant subscription without corrupting existing data (PRD 75).
     */
    public function changePlan(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);
        /** @var Plan $newPlan */
        $newPlan = Plan::findOrFail($validated['plan_id']);

        /** @var Subscription|null $sub */
        $sub = $tenant->currentSubscription()->first();

        $beforePlanId = $sub?->plan_id;

        if ($sub) {
            $sub->plan_id = $newPlan->id;
            $sub->status = 'ACTIVE';
            $sub->save();
        } else {
            Subscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $newPlan->id,
                'status' => 'ACTIVE',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);
        }

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.plan_changed',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'before' => [
                'plan_id' => $beforePlanId,
            ],
            'after' => [
                'plan_id' => $newPlan->id,
                'plan_name' => $newPlan->name,
            ],
            'source' => 'web',
        ]);

        return back()->with('success', "Paket langganan berhasil diubah menjadi '{$newPlan->name}'.");
    }

    /**
     * Extend tenant trial by specified days (PRD 73).
     */
    public function extendTrial(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) $validated['days'];

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);

        /** @var Subscription|null $sub */
        $sub = $tenant->currentSubscription()->first();

        if ($sub) {
            $base = $sub->trial_ends_at && $sub->trial_ends_at->isFuture() ? $sub->trial_ends_at : now();
            $sub->trial_ends_at = $base->addDays($days);
            $sub->status = 'TRIAL';
            $sub->save();
        } else {
            /** @var Plan|null $defaultPlan */
            $defaultPlan = Plan::first();
            $planId = $defaultPlan !== null ? $defaultPlan->id : 1;
            Subscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'status' => 'TRIAL',
                'trial_ends_at' => now()->addDays($days),
            ]);
        }

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.trial_extended',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'after' => [
                'extended_days' => $days,
                'trial_ends_at' => $sub?->trial_ends_at?->toIso8601String(),
            ],
            'source' => 'web',
        ]);

        return back()->with('success', "Masa trial berhasil diperpanjang sebanyak {$days} hari.");
    }

    /**
     * Update internal support notes for tenant (PRD 74).
     */
    public function updateNotes(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $validated = $request->validate([
            'support_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);
        $tenant->support_notes = $validated['support_notes'] ?? null;
        $tenant->save();

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.support_notes_updated',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'source' => 'web',
        ]);

        return back()->with('success', 'Catatan dukungan teknis berhasil disimpan.');
    }

    /**
     * Start secure support access session into tenant workspace (PRD 74).
     * Strictly requires reason, creates audit log visible to both Super Admin and Owner.
     */
    public function supportAccess(Request $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);

        Audit::record([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'actor_type' => 'user',
            'action' => 'super_admin.support_access',
            'entity_type' => 'tenant',
            'entity_id' => $tenant->id,
            'after' => [
                'reason' => (string) $validated['reason'],
                'admin_name' => $user->name,
                'admin_email' => $user->email,
            ],
            'source' => 'web',
        ]);

        // Establish support access session
        $request->session()->put('active_tenant_id', $tenant->id);
        $request->session()->put('support_access_active', true);
        $request->session()->put('support_access_reason', (string) $validated['reason']);
        $request->session()->put('support_tenant_id', $tenant->id);

        return redirect()->route('owner.dashboard')
            ->with('success', "Memasuki sesi dukungan teknis untuk tenant '{$tenant->name}'.");
    }

    /**
     * Exit support access session and return back to Super Admin portal (PRD 74).
     */
    public function exitSupportAccess(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->is_super_admin, 403);

        $tenantId = $request->session()->get('support_tenant_id');

        $request->session()->forget([
            'active_tenant_id',
            'support_access_active',
            'support_access_reason',
            'support_tenant_id',
        ]);

        if ($tenantId) {
            return redirect()->route('admin.tenants.show', $tenantId)
                ->with('success', 'Sesi dukungan teknis telah diakhiri.');
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Sesi dukungan teknis telah diakhiri.');
    }
}
