<?php

namespace App\Http\Controllers\Owner;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = TenantContext::getTenant();

        $business = null;
        $subscription = null;

        if ($tenant) {
            $business = Business::where('tenant_id', $tenant->id)->first();
            $subscription = $tenant->currentSubscription()->with('plan')->first();
        }

        return Inertia::render('Owner/Dashboard', [
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'status' => $tenant->status,
            ] : null,
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'timezone' => $business->timezone,
            ] : null,
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan ? $subscription->plan->name : 'Trial',
                'trial_ends_at' => $subscription->trial_ends_at ? \Illuminate\Support\Carbon::parse($subscription->trial_ends_at)->format('Y-m-d H:i:s') : null,
                'current_period_end' => $subscription->current_period_end ? \Illuminate\Support\Carbon::parse($subscription->current_period_end)->format('Y-m-d H:i:s') : null,
            ] : null,
        ]);
    }
}
