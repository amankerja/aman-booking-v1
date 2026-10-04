<?php

namespace Database\Factories;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'actor_id' => 1,
            'actor_type' => 'user',
            'actor_role' => 'OWNER',
            'action' => 'business.updated',
            'entity_type' => 'Business',
            'entity_id' => '1',
            'before' => ['name' => 'Old Name'],
            'after' => ['name' => 'New Name'],
            'source' => 'web',
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
