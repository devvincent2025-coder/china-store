<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->bothify('VAR-####??')),
            // Null = inherit the parent product's price; tests/seeders that want a distinct price
            // (e.g. a "Carton of 24" costing much more than a "Piece") should override this.
            'price' => null,
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
