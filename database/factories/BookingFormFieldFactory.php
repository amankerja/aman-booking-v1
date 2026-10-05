<?php

namespace Database\Factories;

use App\Domain\Form\Models\BookingForm;
use App\Domain\Form\Models\BookingFormField;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingFormField>
 */
class BookingFormFieldFactory extends Factory
{
    protected $model = BookingFormField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'form_id' => BookingForm::factory(),
            'field_key' => Str::snake($label),
            'type' => 'text',
            'label' => ucfirst($label),
            'placeholder' => 'Masukkan '.strtolower($label),
            'help_text' => null,
            'is_required' => false,
            'default_value' => null,
            'options' => null,
            'validation_rules' => null,
            'visibility_conditions' => null,
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
