<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\VendorStore>
 */
class VendorStoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Store';

        return [
            'vendor_id' => Vendor::factory(),
            'category_id' => null,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'name' => [
                'en' => $name,
                'zh' => $name,
                'fr' => $name,
            ],
            'description' => [
                'en' => 'A trusted marketplace store offering quality products with fast delivery across Nigeria.',
                'zh' => 'A trusted marketplace store offering quality products with fast delivery across Nigeria.',
                'fr' => 'A trusted marketplace store offering quality products with fast delivery across Nigeria.',
            ],
            'logo_url' => 'https://picsum.photos/seed/logo-'.fake()->unique()->numberBetween(1, 99999).'/400/400',
            'banner_url' => 'https://picsum.photos/seed/storefront-'.fake()->unique()->numberBetween(1, 99999).'/1200/400',
            'address_line' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Lagos', 'Abuja']),
            'state' => fake()->randomElement(['Lagos', 'FCT']),
            'country' => 'NG',
        ];
    }
}
