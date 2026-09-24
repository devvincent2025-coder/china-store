<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->words(4, true));
        $price = fake()->numberBetween(200_000, 5_000_000); // kobo

        return [
            'vendor_id' => Vendor::factory(),
            'category_id' => Category::factory(),
            'slug' => Str::slug($title).'-'.Str::random(6),
            'title' => [
                'en' => $title,
                'zh' => $title,
                'fr' => $title,
            ],
            'description' => [
                'en' => 'A quality product offered by one of our verified vendors, carefully selected for durability and value.',
                'zh' => 'A quality product offered by one of our verified vendors, carefully selected for durability and value.',
                'fr' => 'A quality product offered by one of our verified vendors, carefully selected for durability and value.',
            ],
            'sku' => strtoupper(Str::random(8)),
            'price' => $price,
            'compare_price' => null,
            'stock' => fake()->numberBetween(10, 200),
            'status' => 'published',
            'is_featured' => false,
            'views_count' => fake()->numberBetween(0, 500),
        ];
    }
}
