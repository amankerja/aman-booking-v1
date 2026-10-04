<?php

namespace Database\Factories;

use App\Domain\Customer\Models\Customer;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone_e164' => '+6281'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'tags' => ['regular'],
            'marketing_consent_at' => now(),
            'no_show_count' => 0,
        ];
    }
}
