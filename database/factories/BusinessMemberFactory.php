<?php

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\BusinessMember;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMember>
 */
class BusinessMemberFactory extends Factory
{
    protected $model = BusinessMember::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'preset' => 'STAFF', // OWNER, MANAGER, FRONT_DESK, STAFF, VIEWER
            'permissions' => ['view_bookings', 'manage_schedule'],
        ];
    }
}
