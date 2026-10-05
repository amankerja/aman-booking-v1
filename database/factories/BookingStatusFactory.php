<?php

namespace Database\Factories;

use App\Domain\Booking\Enums\BookingStatusCategory;
use App\Domain\Booking\Models\BookingStatus;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingStatus>
 */
class BookingStatusFactory extends Factory
{
    protected $model = BookingStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'category' => BookingStatusCategory::CONFIRMED,
            'color' => '#2563eb',
            'badge_bg' => 'bg-blue-100 text-blue-700',
            'icon' => 'check-circle',
            'sort_order' => fake()->numberBetween(1, 10),
            'is_default' => false,
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }
}
