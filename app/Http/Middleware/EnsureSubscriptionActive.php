<?php

namespace App\Http\Middleware;

use App\Domain\Subscription\Models\Subscription;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::getTenant();

        if (! $tenant) {
            return $next($request);
        }

        /** @var Subscription|null $subscription */
        $subscription = $tenant->currentSubscription()->first();
        $status = strtoupper($subscription ? $subscription->status : 'TRIAL');

        $isPublicRoute = $request->is('b/*') || $request->route('business_slug');

        // 1. Handling SUSPENDED / CANCELLED: Public bookings closed (HTTP 503 TENANT_UNAVAILABLE)
        if (in_array($status, ['SUSPENDED', 'CANCELLED'], true)) {
            if ($isPublicRoute) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return new JsonResponse([
                        'error' => [
                            'code' => 'TENANT_UNAVAILABLE',
                            'message' => 'Layanan booking bisnis ini sedang tidak tersedia.',
                        ],
                    ], 503);
                }

                abort(503, 'Layanan booking bisnis ini sedang tidak tersedia.');
            }

            // On owner workspace (/app/*)
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return new JsonResponse([
                        'error' => [
                            'code' => 'SUBSCRIPTION_SUSPENDED',
                            'message' => 'Akun usaha Anda sedang dinonaktifkan (suspended). Hubungi dukungan untuk informasi lebih lanjut.',
                        ],
                    ], 403);
                }

                abort(403, 'Akun usaha Anda sedang dinonaktifkan (suspended). Hubungi dukungan untuk informasi lebih lanjut.');
            }
        }

        // 2. Handling GRACE_PERIOD & EXPIRED: Read-only access to dashboard data
        if (in_array($status, ['GRACE_PERIOD', 'EXPIRED'], true)) {
            // Block mutating requests (POST, PUT, PATCH, DELETE)
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                $message = $status === 'GRACE_PERIOD'
                    ? 'Langganan Anda dalam masa tenggang (grace period). Sistem beralih ke mode hanya-baca (read-only). Segera perbarui langganan Anda.'
                    : 'Langganan Anda telah kedaluwarsa (expired). Sistem beralih ke mode hanya-baca (read-only). Perbarui paket untuk menambah atau mengubah data.';

                if ($request->expectsJson() || $request->is('api/*')) {
                    return new JsonResponse([
                        'error' => [
                            'code' => 'SUBSCRIPTION_READ_ONLY',
                            'message' => $message,
                        ],
                    ], 403);
                }

                abort(403, $message);
            }
        }

        return $next($request);
    }
}
