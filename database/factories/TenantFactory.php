<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'status' => 'ACTIVE',
            'owner_user_id' => User::factory(),
        ];
    }
}
