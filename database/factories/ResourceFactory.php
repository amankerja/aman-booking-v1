<?php

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Resource\Models\Resource;
use App\Domain\Resource\Models\ResourceType;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'business_id' => Business::factory(),
            'resource_type_id' => fn () => ResourceType::firstOrCreate(
                ['code' => 'STAFF'],
                [
                    'name' => 'Staff / Terapis / Praktisi',
                    'icon' => 'User',
                    'is_staff' => true,
                    'is_space' => false,
                    'is_equipment' => false,
                    'is_active' => true,
                ]
            )->id,
            'uuid' => (string) Str::uuid(),
            'name' => fake()->name(),
            'code' => 'STF-'.fake()->unique()->randomNumber(3),
            'capacity' => 1,
            'visibility' => 'PUBLIC',
            'state' => 'AVAILABLE',
            'skills' => ['Massage', 'Facial'],
            'metadata' => null,
            'archived_at' => null,
        ];
    }
}
