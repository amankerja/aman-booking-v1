<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Service\Models\Service;
use App\Domain\Subscription\Exceptions\PlanLimitReachedException;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionUsage;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use App\Support\TenantContext;

class LimitEnforcer
{
    /**
     * Map friendly entity keys to limit keys and their respective counting callbacks.
     *
     * @var array<string, array{limit_key: string, counter: callable(Tenant): int}>
     */
    protected array $entityMap = [];

    public function __construct()
    {
        $this->entityMap = [
            'businesses' => [
                'limit_key' => 'max_businesses',
                'counter' => fn (Tenant $tenant) => Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
            'business' => [
                'limit_key' => 'max_businesses',
                'counter' => fn (Tenant $tenant) => Business::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
            'members' => [
                'limit_key' => 'max_members',
                'counter' => fn (Tenant $tenant) => BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
            'member' => [
                'limit_key' => 'max_members',
                'counter' => fn (Tenant $tenant) => BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
            'business_members' => [
                'limit_key' => 'max_members',
                'counter' => fn (Tenant $tenant) => BusinessMember::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
            'services' => [
                'limit_key' => 'max_services',
                'counter' => fn (Tenant $tenant) => Service::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('archived_at')->count(),
            ],
            'service' => [
                'limit_key' => 'max_services',
                'counter' => fn (Tenant $tenant) => Service::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('archived_at')->count(),
            ],
            'resources' => [
                'limit_key' => 'max_resources',
                'counter' => fn (Tenant $tenant) => 0,
            ],
            'resource' => [
                'limit_key' => 'max_resources',
                'counter' => fn (Tenant $tenant) => 0,
            ],
            'bookings' => [
                'limit_key' => 'max_monthly_bookings',
                'counter' => function (Tenant $tenant) {
                    $usage = SubscriptionUsage::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('metric', 'monthly_bookings')
                        ->where('period_start', '<=', now())
                        ->where('period_end', '>=', now())
                        ->first();

                    return $usage ? (int) $usage->usage_count : 0;
                },
            ],
            'monthly_bookings' => [
                'limit_key' => 'max_monthly_bookings',
                'counter' => function (Tenant $tenant) {
                    $usage = SubscriptionUsage::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('metric', 'monthly_bookings')
                        ->where('period_start', '<=', now())
                        ->where('period_end', '>=', now())
                        ->first();

                    return $usage ? (int) $usage->usage_count : 0;
                },
            ],
        ];
    }

    /**
     * Check whether a new entity can be created under current subscription plan limits.
     */
    public function canCreate(string $entity, ?Tenant $tenant = null): bool
    {
        $tenant = $tenant ?? TenantContext::getTenant();

        if (! $tenant) {
            return true;
        }

        $usage = $this->getUsage($entity, $tenant);

        if ($usage['is_unlimited']) {
            return true;
        }

        return $usage['current'] < $usage['limit'];
    }

    /**
     * Enforce that a new entity can be created, or throw PlanLimitReachedException.
     *
     * @throws PlanLimitReachedException
     */
    public function enforce(string $entity, ?Tenant $tenant = null): void
    {
        if (! $this->canCreate($entity, $tenant)) {
            throw new PlanLimitReachedException(
                $entity,
                'Batas paket Anda sudah tercapai. Upgrade paket untuk menambah.'
            );
        }
    }

    /**
     * Get usage metrics (current, limit, remaining, is_unlimited) for an entity.
     *
     * @return array{current: int, limit: int, remaining: int, is_unlimited: bool}
     */
    public function getUsage(string $entity, ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? TenantContext::getTenant();

        if (! $tenant) {
            return [
                'current' => 0,
                'limit' => -1,
                'remaining' => PHP_INT_MAX,
                'is_unlimited' => true,
            ];
        }

        $config = $this->entityMap[$entity] ?? null;
        $limitKey = $config ? $config['limit_key'] : $entity;
        $counter = $config ? $config['counter'] : fn () => 0;

        $currentCount = $counter($tenant);
        $limit = $this->resolveLimit($tenant, $limitKey);

        $isUnlimited = ($limit === -1 || $limit >= 9999);
        $remaining = $isUnlimited ? PHP_INT_MAX : max(0, $limit - $currentCount);

        return [
            'current' => $currentCount,
            'limit' => $limit,
            'remaining' => $remaining,
            'is_unlimited' => $isUnlimited,
        ];
    }

    /**
     * Resolve limit value from current tenant subscription plan.
     */
    protected function resolveLimit(Tenant $tenant, string $limitKey): int
    {
        /** @var Subscription|null $subscription */
        $subscription = $tenant->currentSubscription()->with('plan')->first();

        /** @var Plan|null $plan */
        $plan = $subscription ? $subscription->plan : Plan::where('code', 'BASIC')->first();

        if (! $plan) {
            return -1;
        }

        $limits = $plan->limits;
        if (! is_array($limits) || ! isset($limits[$limitKey])) {
            return -1;
        }

        return (int) $limits[$limitKey];
    }
}
