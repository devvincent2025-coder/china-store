<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        $title = ucfirst(fake()->words(3, true));

        return [
            'vendor_id' => null,
            'title' => [
                'en' => $title,
                'zh' => $title,
                'fr' => $title,
            ],
            'image_url' => 'https://picsum.photos/seed/banner-'.fake()->unique()->numberBetween(1, 99999).'/1600/600',
            'link_url' => '/products',
            'placement' => 'homepage',
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
