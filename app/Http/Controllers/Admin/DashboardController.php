<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Booking\Models\Booking;
use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
use App\Domain\Payment\Models\Invoice;
use App\Domain\Payment\Models\Payment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->is_super_admin, 403, 'Akses ditolak. Halaman ini hanya untuk Super Admin.');

        $metrics = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Subscription::withoutGlobalScopes()->where('status', 'ACTIVE')->count(),
            'trial_tenants' => Subscription::withoutGlobalScopes()->where('status', 'TRIAL')->count(),
            'expired_tenants' => Subscription::withoutGlobalScopes()->where('status', 'EXPIRED')->count(),
            'suspended_tenants' => Tenant::where('status', 'SUSPENDED')->count(),
            'total_businesses' => Business::withoutGlobalScopes()->count(),
            'total_users' => User::count(),
            'bookings_today' => Booking::withoutGlobalScopes()->whereDate('start_at', today())->count(),
            'bookings_this_month' => Booking::withoutGlobalScopes()
                ->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'subscription_revenue' => (int) Invoice::withoutGlobalScopes()->where('status', 'PAID')->sum('amount_paid_idr'),
            'failed_payments' => Payment::withoutGlobalScopes()->where('status', 'FAILED')->count(),
            'system_health' => 'OPERATIONAL',
        ];

        $recentActivity = AuditLog::withoutGlobalScopes()
            ->with('actor')
            ->latest()
            ->limit(10)
            ->get();

        $recentTenants = Tenant::with(['owner', 'business', 'currentSubscription.plan'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'metrics' => $metrics,
            'recentActivity' => $recentActivity,
            'recentTenants' => $recentTenants,
        ]);
    }
}
