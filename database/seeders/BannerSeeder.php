<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Coupon;
use App\Models\Vendor;
use App\Services\Seeding\ProductImageSearchService;
use Illuminate\Database\Seeder;

/**
 * Homepage hero carousel: promotional slides for new arrivals, coupon codes, and category
 * deals — not random stock photos. Also seeds 1–2 store banners per vendor.
 *
 * Homepage slides use curated, high-resolution images bundled in public/images/banners/
 * (Unsplash License — free for commercial use; source photo noted next to each).
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $images = app(ProductImageSearchService::class);

        $welcome = Coupon::query()->where('code', 'WELCOME20')->first();
        $newArrival = Coupon::query()->where('code', 'NEWARRIVAL15')->first();
        $flash = Coupon::query()->where('code', 'FLASH5000')->first();

        $homepageBanners = [
            [
                'title' => [
                    'en' => 'New Arrivals — Fresh Stock This Week',
                    'zh' => '本周新品到货',
                    'fr' => 'Nouveautés — Arrivages de la semaine',
                ],
                'image' => '/images/banners/new-arrivals.jpg', // unsplash.com/photos/P3pI6xzovu0
                'link_url' => '/products',
            ],
            [
                'title' => [
                    'en' => $welcome
                        ? "Save {$welcome->value}% with code {$welcome->code}"
                        : 'Welcome Offer — Save on Your First Order',
                    'zh' => $welcome ? "使用优惠码 {$welcome->code} 立减 {$welcome->value}%" : '新客优惠',
                    'fr' => $welcome
                        ? "Économisez {$welcome->value}% avec le code {$welcome->code}"
                        : 'Offre de bienvenue',
                ],
                'image' => '/images/banners/welcome-offer.jpg', // unsplash.com/photos/BQ9usyzHx_w
                'link_url' => '/products',
            ],
            [
                'title' => [
                    'en' => 'Electronics Deals from Verified Vendors',
                    'zh' => '认证商家电子产品特惠',
                    'fr' => 'Promos électronique de vendeurs vérifiés',
                ],
                'image' => '/images/banners/electronics-deals.jpg', // unsplash.com/photos/MP56QwyNC8c
                'link_url' => '/categories/electronics',
            ],
            [
                'title' => [
                    'en' => $flash
                        ? "Flash Sale — ₦".number_format($flash->value / 100)." off with {$flash->code}"
                        : 'Flash Sale — Limited Time Offers',
                    'zh' => $flash ? "限时闪购 — 优惠码 {$flash->code}" : '限时闪购',
                    'fr' => $flash
                        ? "Vente flash — code {$flash->code}"
                        : 'Vente flash',
                ],
                'image' => '/images/banners/flash-sale.jpg', // unsplash.com/photos/Wt33T42JNCM
                'link_url' => '/products',
            ],
            [
                'title' => [
                    'en' => $newArrival
                        ? "Fashion New In — {$newArrival->value}% off with {$newArrival->code}"
                        : 'Fashion New In — Shop the Latest Looks',
                    'zh' => $newArrival ? "时尚新品 — {$newArrival->code} 减 {$newArrival->value}%" : '时尚新品',
                    'fr' => $newArrival
                        ? "Mode nouveautés — {$newArrival->value}% avec {$newArrival->code}"
                        : 'Nouveautés mode',
                ],
                'image' => '/images/banners/fashion-new-in.jpg', // unsplash.com/photos/qnKhZJPKFD8
                'link_url' => '/categories/fashion',
            ],
        ];

        foreach ($homepageBanners as $index => $banner) {
            $this->command?->info('Fetching promo banner: '.$banner['title']['en']);

            Banner::updateOrCreate(
                ['vendor_id' => null, 'sort_order' => $index, 'placement' => 'homepage'],
                [
                    'title' => $banner['title'],
                    'image_url' => $banner['image'],
                    'link_url' => $banner['link_url'],
                    'is_active' => true,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonths(2),
                ]
            );
        }

        $vendors = Vendor::with('store.category')->get();

        foreach ($vendors as $vendor) {
            $storeName = $vendor->store?->getTranslation('name', 'en') ?? $vendor->business_name;
            $category = $vendor->store?->category?->getTranslation('name', 'en') ?? 'shop';
            $bannerCount = 2;

            for ($i = 0; $i < $bannerCount; $i++) {
                Banner::updateOrCreate(
                    ['vendor_id' => $vendor->id, 'sort_order' => $i, 'placement' => 'store'],
                    [
                        'title' => [
                            'en' => $i === 0 ? "Welcome to {$storeName}" : "New at {$storeName}",
                            'zh' => $i === 0 ? "欢迎光临 {$storeName}" : "{$storeName} 新品",
                            'fr' => $i === 0 ? "Bienvenue chez {$storeName}" : "Nouveautés chez {$storeName}",
                        ],
                        'image_url' => $images->findWide($category.' products store display', $i, 'retail store interior'),
                        'link_url' => '/stores/'.($vendor->store?->slug ?? ''),
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
