<?php

namespace Database\Factories;

use App\Domain\Subscription\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('PLAN-???'),
            'name' => fake()->words(2, true),
            'price_idr' => 150000,
            'billing_cycle' => 'MONTHLY',
            'limits' => [
                'max_businesses' => 1,
                'max_members' => 3,
                'max_services' => 10,
                'max_resources' => 5,
                'max_monthly_bookings' => 100,
                'audit_log_retention_days' => 30,
            ],
            'features' => [
                'whatsapp_notifications' => true,
                'online_payments' => true,
                'inventory' => false,
                'advanced_reports' => false,
                'custom_domain' => false,
                'api_webhooks' => false,
            ],
            'is_active' => true,
        ];
    }
}
