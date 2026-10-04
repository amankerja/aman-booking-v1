<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Business\Models\Business;
use App\Domain\Identity\Models\User;
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
            'total_businesses' => Business::withoutGlobalScopes()->count(),
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::withoutGlobalScopes()->where('status', 'ACTIVE')->count(),
            'trial_subscriptions' => Subscription::withoutGlobalScopes()->where('status', 'TRIAL')->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'metrics' => $metrics,
        ]);
    }
}
