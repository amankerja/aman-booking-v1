<?php

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Service\Models\Service;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'business_id' => Business::factory(),
            'uuid' => (string) Str::uuid(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->sentence(),
            'price_idr' => fake()->randomElement([50000, 75000, 100000, 150000, 250000]),
            'duration_type' => 'FIXED',
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'buffer_before' => 0,
            'buffer_after' => 0,
            'capacity' => 1,
            'is_active' => true,
            'is_featured' => false,
        ];
    }
}
