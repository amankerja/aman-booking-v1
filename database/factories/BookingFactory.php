<?php

namespace Database\Factories;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\Booking;
use App\Domain\Customer\Models\Customer;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = now()->addDay()->setTime(10, 0);
        $endAt = $startAt->copy()->addMinutes(60);

        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'BK-'.now()->format('Ymd').'-'.fake()->numerify('#####'),
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'service_snapshot' => [
                'name' => 'Layanan Contoh',
                'duration_minutes' => 60,
                'price_idr' => 150000,
                'buffer_before' => 0,
                'buffer_after' => 0,
            ],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'business_timezone' => 'Asia/Jakarta',
            'status_category' => BookingStatusCategory::CONFIRMED,
            'payment_status' => 'UNPAID',
            'total_idr' => 150000,
            'deposit_idr' => 0,
            'source' => 'PUBLIC',
            'hold_expires_at' => null,
            'reschedule_count' => 0,
            'manage_token' => hash('sha256', Str::random(40)),
            'manage_token_expires_at' => now()->addDays(30),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
