<?php

namespace Database\Factories;

use App\Domain\Tenant\Models\Tenant;
use App\Support\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => (string) Str::uuid(),
            'endpoint' => '/api/v1/bookings',
            'request_hash' => hash('sha256', Str::random(32)),
            'response_snapshot' => ['status' => 'success'],
            'status_code' => 200,
            'expires_at' => now()->addHours(24),
        ];
    }
}
