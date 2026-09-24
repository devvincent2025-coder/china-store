<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        $url = 'https://picsum.photos/seed/product-'.fake()->unique()->numberBetween(1, 99999).'/800/800';

        return [
            'product_id' => Product::factory(),
            'original_url' => $url,
            // Factories stay offline; seeders fetch real matched photos via ProductImageSearchService.
            'enhanced_url' => $url,
            'storage_path' => null,
            'processing_status' => 'done',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => ['is_primary' => true]);
    }
}
