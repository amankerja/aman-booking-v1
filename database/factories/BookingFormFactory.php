<?php

namespace Database\Factories;

use App\Domain\Form\Models\BookingForm;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingForm>
 */
class BookingFormFactory extends Factory
{
    protected $model = BookingForm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true).' Form';

        return [
            'tenant_id' => Tenant::factory(),
            'service_id' => null,
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
