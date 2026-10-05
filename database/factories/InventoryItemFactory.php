<?php

namespace Database\Factories;

use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);
        $initialStock = fake()->numberBetween(10, 100);

        return [
            'tenant_id' => Tenant::factory(),
            'business_id' => Business::factory(),
            'uuid' => (string) Str::uuid(),
            'sku' => 'SKU-'.fake()->unique()->randomNumber(5),
            'name' => ucfirst($name),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Bahan', 'Obat', 'Minyak', 'Perlengkapan']),
            'unit' => fake()->randomElement(['pcs', 'ml', 'box', 'botol']),
            'cost_price_idr' => fake()->numberBetween(10000, 50000),
            'sale_price_idr' => fake()->numberBetween(25000, 100000),
            'initial_stock' => $initialStock,
            'current_stock' => $initialStock,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
            'allow_negative_stock' => false,
            'is_active' => true,
        ];
    }
}
