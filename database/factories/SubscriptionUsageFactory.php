<?php

namespace Database\Factories;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionUsage;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionUsage>
 */
class SubscriptionUsageFactory extends Factory
{
    protected $model = SubscriptionUsage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'metric' => 'monthly_bookings',
            'usage_count' => fake()->numberBetween(0, 50),
        ];
    }
}
