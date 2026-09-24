<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => [
                'en' => $name,
                'zh' => $name,
                'fr' => $name,
            ],
            'image_url' => 'https://picsum.photos/seed/'.Str::slug($name).'/800/800',
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
