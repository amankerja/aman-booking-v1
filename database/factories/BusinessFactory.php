<?php

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'tenant_id' => Tenant::factory(),
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'name' => $name,
            'timezone' => 'Asia/Jakarta',
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'settings' => [
                'currency' => 'IDR',
                'locale' => 'id',
            ],
            'published_at' => now(),
        ];
    }
}
