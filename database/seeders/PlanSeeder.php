<?php

namespace Database\Seeders;

use App\Domain\Subscription\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'BASIC',
                'name' => 'Paket Basic',
                'price_idr' => 99000,
                'billing_cycle' => 'MONTHLY',
                'limits' => [
                    'max_businesses' => 1,
                    'max_members' => 3,
                    'max_services' => 5,
                    'max_resources' => 2,
                    'max_monthly_bookings' => 100,
                    'audit_log_retention_days' => 30,
                ],
                'features' => [
                    'booking_page' => true,
                    'workflow' => 'template',
                    'form_builder' => 'basic',
                    'whatsapp_notifications' => false,
                    'online_payments' => false,
                    'inventory' => false,
                    'reports' => 'basic',
                    'api_webhooks' => false,
                    'custom_domain' => false,
                ],
                'is_active' => true,
            ],
            [
                'code' => 'PRO',
                'name' => 'Paket Pro',
                'price_idr' => 249000,
                'billing_cycle' => 'MONTHLY',
                'limits' => [
                    'max_businesses' => 1,
                    'max_members' => 10,
                    'max_services' => 50,
                    'max_resources' => 30,
                    'max_monthly_bookings' => 1000,
                    'audit_log_retention_days' => 180,
                ],
                'features' => [
                    'booking_page' => true,
                    'workflow' => 'builder_full',
                    'form_builder' => 'advanced',
                    'whatsapp_notifications' => true,
                    'online_payments' => true,
                    'inventory' => true,
                    'reports' => 'intermediate',
                    'api_webhooks' => false,
                    'custom_domain' => false,
                ],
                'is_active' => true,
            ],
            [
                'code' => 'BUSINESS',
                'name' => 'Paket Business',
                'price_idr' => 599000,
                'billing_cycle' => 'MONTHLY',
                'limits' => [
                    'max_businesses' => 999,
                    'max_members' => 999,
                    'max_services' => 9999,
                    'max_resources' => 9999,
                    'max_monthly_bookings' => 99999,
                    'audit_log_retention_days' => 365,
                ],
                'features' => [
                    'booking_page' => true,
                    'workflow' => 'builder_advanced',
                    'form_builder' => 'advanced',
                    'whatsapp_notifications' => true,
                    'online_payments' => true,
                    'inventory' => true,
                    'reports' => 'advanced',
                    'api_webhooks' => true,
                    'custom_domain' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }
}
