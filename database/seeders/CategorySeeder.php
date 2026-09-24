<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Services\Seeding\ProductImageSearchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the top-level category tree the whole catalog hangs off of. Must run before VendorSeeder
 * and ProductSeeder, both of which link records to these categories.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $images = app(ProductImageSearchService::class);

        $categories = [
            ['en' => 'Electronics', 'zh' => '电子产品', 'fr' => 'Électronique', 'query' => 'consumer electronics gadgets'],
            ['en' => 'Home & Kitchen', 'zh' => '家居厨房', 'fr' => 'Maison et Cuisine', 'query' => 'kitchen cookware utensils'],
            ['en' => 'Fashion', 'zh' => '时尚', 'fr' => 'Mode', 'query' => 'fashion clothing apparel'],
            ['en' => 'Beauty', 'zh' => '美妆', 'fr' => 'Beauté', 'query' => 'cosmetics makeup beauty products'],
            ['en' => 'Toys', 'zh' => '玩具', 'fr' => 'Jouets', 'query' => 'children toys play'],
            ['en' => 'Baby & Kids', 'zh' => '母婴用品', 'fr' => 'Bébé et Enfants', 'query' => 'baby products stroller'],
        ];

        foreach ($categories as $index => $names) {
            $this->command?->info('Fetching category image: '.$names['en']);

            Category::updateOrCreate(
                ['slug' => Str::slug($names['en'])],
                [
                    'parent_id' => null,
                    'name' => [
                        'en' => $names['en'],
                        'zh' => $names['zh'],
                        'fr' => $names['fr'],
                    ],
                    'image_url' => $images->find($names['query'], 0, $names['en']),
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
