<?php

namespace App\Http\Middleware;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Resolve via header (API / testing)
        if ($headerTenantId = $request->header('X-Tenant-ID')) {
            $tenant = Tenant::find($headerTenantId);
        }

        // 2. Resolve via route parameter (slug or tenant)
        if (! $tenant && $request->route('business_slug')) {
            $business = Business::withoutGlobalScopes()
                ->where('slug', $request->route('business_slug'))
                ->first();
            $tenant = $business?->tenant;
        }

        // 3. Resolve via authenticated user
        if (! $tenant && $user = $request->user()) {
            // Check session selected tenant or default to user's first owned or member tenant
            $sessionTenantId = $request->session()->get('active_tenant_id');
            if ($sessionTenantId) {
                $tenant = Tenant::find($sessionTenantId);
            }

            if (! $tenant) {
                $tenant = $user->ownedTenants()->first();
            }

            if (! $tenant) {
                $membership = $user->memberships()->first();
                $tenant = $membership?->tenant;
            }
        }

        if ($tenant) {
            TenantContext::setTenant($tenant);
        } else {
            TenantContext::clear();
        }

        return $next($request);
    }
}
