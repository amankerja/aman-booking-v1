<?php

namespace App\Http\Middleware;

use App\Domain\Business\Models\Business;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = TenantContext::getTenant();
        $user = $request->user();

        $business = null;
        $subscription = null;

        if ($tenant) {
            $business = Business::where('tenant_id', $tenant->id)->first();
            $currSub = $tenant->currentSubscription()->with('plan')->first();
            if ($currSub) {
                $statusVal = (string) $currSub->status;
                $subscription = [
                    'status' => $statusVal,
                    'plan_name' => $currSub->plan ? $currSub->plan->name : 'Trial',
                ];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                ] : null,
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                ] : null,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'settings' => $business->settings,
                'inventory_enabled' => ! empty($business->settings['modules']['inventory']) || ! empty($business->settings['inventory_enabled']),
            ] : null,
            'subscription' => $subscription,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
