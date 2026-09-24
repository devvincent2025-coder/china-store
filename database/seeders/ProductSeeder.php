<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Vendor;
use App\Services\Seeding\ProductImageSearchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Generates ~50 demo products spread across the 4 seed vendors and 5-6 categories, each with a
 * few images and (for ~30%) variants, so the storefront/vendor dashboard has realistic data to
 * browse, filter and add to cart. Depends on CategorySeeder and VendorSeeder having already run.
 *
 * Product photos are looked up live from Wikimedia Commons / Openverse using each product's
 * English title so the image matches the name (not a random placeholder).
 */
class ProductSeeder extends Seeder
{
    /**
     * Originally-written generic product name/description pairs per category, with plausible
     * zh/fr translations where confident, falling back to English otherwise. Kobo price ranges
     * are per the spec (NGN x 100).
     */
    private array $catalog = [
        'Electronics' => [
            'price_range' => [1_500_00, 80_000_00],
            'products' => [
                ['en' => 'Wireless Bluetooth Earbuds with Charging Case', 'zh' => '无线蓝牙耳机及充电盒', 'fr' => 'Écouteurs Bluetooth sans fil avec boîtier de charge', 'q' => 'wireless bluetooth earbuds'],
                ['en' => 'Fast-Charging Power Bank 20000mAh', 'zh' => '20000毫安快充移动电源', 'fr' => 'Batterie externe à charge rapide 20000mAh', 'q' => 'portable power bank battery'],
                ['en' => 'Smart LED Desk Lamp with USB Charging Port', 'zh' => '智能LED台灯带USB充电口', 'fr' => 'Lampe de bureau LED intelligente avec port USB', 'q' => 'LED desk lamp'],
                ['en' => 'Portable Bluetooth Speaker, Waterproof', 'zh' => '便携式防水蓝牙音箱', 'fr' => 'Enceinte Bluetooth portable étanche', 'q' => 'waterproof bluetooth speaker'],
                ['en' => 'Universal Phone Holder Car Mount', 'zh' => '通用手机车载支架', 'fr' => 'Support téléphone universel pour voiture', 'q' => 'car phone holder mount'],
                ['en' => 'Noise-Cancelling Over-Ear Headphones', 'zh' => '主动降噪头戴式耳机', 'fr' => 'Casque à réduction de bruit', 'q' => 'noise cancelling headphones'],
                ['en' => 'Smart Watch with Fitness Tracker', 'zh' => '智能运动手表', 'fr' => 'Montre intelligente avec suivi de fitness', 'q' => 'smartwatch fitness tracker'],
                ['en' => 'Multi-Port USB-C Charging Hub', 'zh' => '多口USB-C充电枢纽', 'fr' => 'Hub de charge USB-C multiport', 'q' => 'USB-C hub charger'],
                ['en' => 'Mini Digital Kitchen Scale', 'zh' => '迷你数字厨房秤', 'fr' => 'Mini balance de cuisine numérique', 'q' => 'kitchen food scale'],
                ['en' => 'HD Webcam with Built-In Microphone', 'zh' => '高清网络摄像头带麦克风', 'fr' => 'Webcam HD avec microphone intégré', 'q' => 'computer webcam'],
                ['en' => 'Compact Bluetooth Keyboard and Mouse Combo', 'zh' => '紧凑型蓝牙键鼠套装', 'fr' => 'Combo clavier et souris Bluetooth compact', 'q' => 'bluetooth keyboard mouse'],
                ['en' => 'Dual-Port Car Phone Charger', 'zh' => '双口车载手机充电器', 'fr' => 'Chargeur voiture double port pour téléphone', 'q' => 'car USB phone charger'],
            ],
        ],
        'Home & Kitchen' => [
            'price_range' => [400_00, 40_000_00],
            'products' => [
                ['en' => 'Non-Stick Frying Pan Set, 3 Pieces', 'zh' => '不粘煎锅三件套', 'fr' => "Ensemble de poêles antiadhésives, 3 pièces", 'q' => 'nonstick frying pan'],
                ['en' => 'Stainless Steel Vacuum Flask 1L', 'zh' => '不锈钢真空保温瓶1升', 'fr' => 'Bouteille isotherme en acier inoxydable 1L', 'q' => 'stainless steel thermos flask'],
                ['en' => 'Electric Blender with Multiple Speed Settings', 'zh' => '多档速度电动搭拌机', 'fr' => 'Mélangeur électrique à plusieurs vitesses', 'q' => 'electric kitchen blender'],
                ['en' => 'Set of 6 Ceramic Dinner Plates', 'zh' => '陶瓷餐盘六件套', 'fr' => 'Ensemble de 6 assiettes en céramique', 'q' => 'ceramic dinner plates'],
                ['en' => 'Foldable Laundry Storage Basket', 'zh' => '可折叠洗衣收纳篮', 'fr' => 'Panier de rangement pliable pour linge', 'q' => 'laundry basket'],
                ['en' => 'Automatic Electric Kettle 1.8L', 'zh' => '自动电热水壶1.8升', 'fr' => 'Bouilloire électrique automatique 1.8L', 'q' => 'electric kettle'],
                ['en' => 'Bamboo Cutting Board with Handle', 'zh' => '带手柄竹制切菜板', 'fr' => 'Planche à découper en bambou avec poignée', 'q' => 'bamboo cutting board'],
                ['en' => 'Adjustable Curtain Rod, 1.2-2.4m', 'zh' => '可调节窗帘杆1.2-2.4米', 'fr' => 'Tringle à rideau ajustable, 1.2-2.4m', 'q' => 'curtain rod'],
                ['en' => 'Non-Electric Food Storage Container Set', 'zh' => '食品储存容器套装', 'fr' => 'Ensemble de contenants de conservation alimentaire', 'q' => 'food storage containers'],
                ['en' => 'Multi-Layer Dish Drying Rack', 'zh' => '多层碗碟晾干架', 'fr' => 'Égouttoir à vaisselle multi-niveaux', 'q' => 'dish drying rack'],
            ],
        ],
        'Fashion' => [
            'price_range' => [500_00, 30_000_00],
            'products' => [
                ['en' => 'Men\'s Slim-Fit Cotton Polo Shirt', 'zh' => '男士修身棉质POLO衫', 'fr' => 'Polo en coton coupe slim pour homme', 'q' => 'men cotton polo shirt'],
                ['en' => 'Women\'s Flowy Summer Maxi Dress', 'zh' => '女士飘逸夏季长裙', 'fr' => 'Robe longue fluide pour femme, été', 'q' => 'women maxi dress'],
                ['en' => 'Unisex Canvas Sneakers', 'zh' => '中性帆布运动鞋', 'fr' => 'Baskets en toile unisexe', 'q' => 'canvas sneakers shoes'],
                ['en' => 'Leather-Look Crossbody Handbag', 'zh' => '仿皮斜挎手提包', 'fr' => 'Sac bandoulière imitation cuir', 'q' => 'crossbody handbag'],
                ['en' => 'Classic Denim Jacket', 'zh' => '经典牛仔夹克', 'fr' => 'Veste en jean classique', 'q' => 'denim jacket'],
                ['en' => 'Ankara Print Headwrap Set', 'zh' => 'Ankara印花头巾套装', 'fr' => "Ensemble de foulards imprimés Ankara", 'q' => 'ankara headwrap'],
                ['en' => 'Men\'s Formal Leather Belt', 'zh' => '男士正装皮带', 'fr' => 'Ceinture en cuir formelle pour homme', 'q' => 'leather belt'],
                ['en' => 'Kids Graphic Print T-Shirt', 'zh' => '儿童印花T恤', 'fr' => 'T-shirt imprimé pour enfants', 'q' => 'kids t-shirt'],
                ['en' => 'Unisex Aviator Sunglasses', 'zh' => '中性飞行员太阳镜', 'fr' => 'Lunettes de soleil aviateur unisexe', 'q' => 'aviator sunglasses'],
                ['en' => 'Women\'s Wide-Brim Sun Hat', 'zh' => '女士宽边遮阳帽', 'fr' => 'Chapeau de soleil à large bord pour femme', 'q' => 'wide brim sun hat'],
            ],
        ],
        'Beauty' => [
            'price_range' => [200_00, 15_000_00],
            'products' => [
                ['en' => 'Hydrating Facial Serum with Vitamin C', 'zh' => '维C保湿精华液', 'fr' => 'Sérum visage hydratant à la vitamine C', 'q' => 'vitamin C facial serum'],
                ['en' => 'Matte Liquid Lipstick Set, 6 Shades', 'zh' => '哑光唇釉套装6色', 'fr' => "Ensemble de rouges à lèvres mats, 6 teintes", 'q' => 'liquid lipstick'],
                ['en' => 'Shea Butter Body Lotion', 'zh' => '乳木果身体乳', 'fr' => 'Lotion corporelle au beurre de karité', 'q' => 'shea butter lotion'],
                ['en' => 'Natural Bristle Makeup Brush Set', 'zh' => '天然鬃毛化妆刷套装', 'fr' => 'Ensemble de pinceaux de maquillage naturels', 'q' => 'makeup brushes'],
                ['en' => 'Charcoal Deep Cleansing Face Mask', 'zh' => '活性炭深层清洁面膜', 'fr' => 'Masque visage purifiant au charbon', 'q' => 'charcoal face mask'],
                ['en' => 'Argan Oil Hair Treatment', 'zh' => '摩洛哥坚果油护发精油', 'fr' => "Traitement capillaire à l'huile d'argan", 'q' => 'argan oil hair'],
                ['en' => 'Rechargeable Hair Clipper Set', 'zh' => '充电式理发器套装', 'fr' => 'Tondeuse à cheveux rechargeable', 'q' => 'hair clipper'],
                ['en' => 'Rose Gold Manicure Tool Kit', 'zh' => '玫瑰金美甲工具套装', 'fr' => 'Kit de manucure doré rose', 'q' => 'manicure kit'],
            ],
        ],
        'Toys' => [
            'price_range' => [300_00, 20_000_00],
            'products' => [
                ['en' => 'Building Blocks Set, 200 Pieces', 'zh' => '积木套装200块', 'fr' => 'Ensemble de blocs de construction, 200 pièces', 'q' => 'building blocks toys'],
                ['en' => 'Remote-Control Racing Car', 'zh' => '遥控赛车', 'fr' => 'Voiture de course télécommandée', 'q' => 'remote control race car'],
                ['en' => 'Plush Teddy Bear, 40cm', 'zh' => '毛绒泰迪熊40厘米', 'fr' => 'Ours en peluche, 40cm', 'q' => 'teddy bear plush'],
                ['en' => 'Educational Puzzle Board for Toddlers', 'zh' => '幼儿益智拼图板', 'fr' => 'Puzzle éducatif pour tout-petits', 'q' => 'toddler puzzle board'],
                ['en' => 'Colorful Stacking Rings Toy', 'zh' => '彩色叠环玩具', 'fr' => "Jouet d'anneaux empilables colorés", 'q' => 'stacking rings toy'],
                ['en' => 'Kids Musical Toy Piano', 'zh' => '儿童音乐玩具钢琴', 'fr' => 'Piano jouet musical pour enfants', 'q' => 'toy piano kids'],
            ],
        ],
        'Baby & Kids' => [
            'price_range' => [500_00, 25_000_00],
            'products' => [
                ['en' => 'Soft Cotton Baby Onesie Set, 3 Pack', 'zh' => '婴儿纯棉连体衣三件套', 'fr' => "Ensemble de 3 bodys en coton pour bébé", 'q' => 'baby onesie'],
                ['en' => 'Foldable Baby Stroller', 'zh' => '可折叠婴儿推车', 'fr' => 'Poussette bébé pliable', 'q' => 'baby stroller'],
                ['en' => 'BPA-Free Baby Feeding Bottle Set', 'zh' => '无BPA婴儿喂养奶瓶套装', 'fr' => 'Ensemble de biberons sans BPA', 'q' => 'baby feeding bottle'],
                ['en' => 'Kids Adjustable Bicycle Helmet', 'zh' => '儿童可调节自行车头盔', 'fr' => "Casque de vélo ajustable pour enfants", 'q' => 'kids bicycle helmet'],
                ['en' => 'Portable Baby Diaper Changing Mat', 'zh' => '便携式婴儿换尿布垫', 'fr' => 'Tapis à langer portable pour bébé', 'q' => 'baby changing mat'],
            ],
        ],
    ];

    public function run(): void
    {
        $images = app(ProductImageSearchService::class);
        $vendors = Vendor::with('store')->get();
        $categories = Category::all()->keyBy(fn ($c) => $c->getTranslation('name', 'en'));

        if ($vendors->isEmpty() || $categories->isEmpty()) {
            $this->command?->warn('ProductSeeder: no vendors or categories found — run VendorSeeder and CategorySeeder first.');

            return;
        }

        $featuredTargetCount = 9;
        $featuredAssigned = 0;
        $productIndex = 0;
        $totalProducts = collect($this->catalog)->sum(fn ($c) => count($c['products']));

        foreach ($this->catalog as $categoryName => $config) {
            $category = $categories->get($categoryName);
            if (! $category) {
                continue;
            }

            [$minPrice, $maxPrice] = $config['price_range'];

            foreach ($config['products'] as $names) {
                $productIndex++;
                $this->command?->info("[{$productIndex}/{$totalProducts}] Fetching images for: {$names['en']}");

                // Distribute across the 4 vendors; the vendor whose store matches this category
                // gets first refusal, otherwise round-robin so every vendor sells a few products
                // outside their main specialty too.
                $matchingVendor = $vendors->first(fn ($v) => $v->store && $v->store->category_id === $category->id);
                $vendor = ($productIndex % 3 === 0 || ! $matchingVendor)
                    ? $vendors[$productIndex % $vendors->count()]
                    : $matchingVendor;

                $price = fake()->numberBetween($minPrice, $maxPrice);
                $hasComparePrice = fake()->boolean(30);
                $isFeatured = $featuredAssigned < $featuredTargetCount && fake()->boolean(20);

                $product = Product::create([
                    'vendor_id' => $vendor->id,
                    'category_id' => $category->id,
                    'slug' => Str::slug($names['en']).'-'.Str::random(6),
                    'title' => $names,
                    'description' => [
                        'en' => 'A carefully selected, quality item from one of our verified vendors. Built to last, backed by fast delivery and easy returns across Nigeria.',
                        'zh' => '来自我们认证供应商的精选优质商品，经久耐用，配送快捷，全尼日利亚支持轻松退货。',
                        'fr' => "Un article de qualité soigneusement sélectionné auprès d'un de nos vendeurs vérifiés, livré rapidement partout au Nigeria.",
                    ],
                    'sku' => strtoupper(Str::random(4)).'-'.fake()->numerify('#####'),
                    'price' => $price,
                    'compare_price' => $hasComparePrice ? (int) round($price * fake()->randomFloat(2, 1.15, 1.25)) : null,
                    'stock' => fake()->numberBetween(10, 200),
                    'status' => 'published',
                    'is_featured' => $isFeatured,
                    'views_count' => fake()->numberBetween(0, 500),
                ]);

                if ($isFeatured) {
                    $featuredAssigned++;
                }

                $imageCount = fake()->numberBetween(2, 3);
                for ($i = 0; $i < $imageCount; $i++) {
                    $url = $images->find($names['q'] ?? $names['en'], $i, $categoryName);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'original_url' => $url,
                        'enhanced_url' => $url,
                        'storage_path' => null,
                        'processing_status' => 'done',
                        'is_primary' => $i === 0,
                        'sort_order' => $i,
                    ]);
                }

                $this->maybeAddOptions($product);
            }
        }
    }

    /**
     * Demonstrates the packaging/color option system end to end: ~35% of products get a
     * "Packaging" option (Piece / Pack of N / Carton of N, with per-tier bulk-discount pricing —
     * NOT a flat delta, since a carton can cost 20x a single piece), ~25% get a "Color" option, and
     * a product can have both at once (producing the full combination matrix, e.g. "Carton of 24 /
     * Red"). A product with neither option is sold directly off its own price/stock, unchanged.
     */
    private function maybeAddOptions(Product $product): void
    {
        $options = [];

        if (fake()->boolean(35)) {
            $tiers = fake()->randomElement([
                [1, 6, 24],   // Piece / Pack of 6 / Carton of 24
                [1, 12, 50],  // Piece / Pack of 12 / Bale of 50
                [1, 10],      // Piece / Pack of 10
            ]);

            $options[] = [
                'name' => ['en' => 'Packaging', 'zh' => '包装', 'fr' => 'Emballage'],
                'values' => collect($tiers)->map(fn ($units) => $this->packagingLabel($units))->all(),
                'prices' => collect($tiers)->map(fn ($units) => $this->packagingPrice($product->price, $units))->all(),
            ];
        }

        if (fake()->boolean(25)) {
            $colorPalette = [
                ['en' => 'Red', 'zh' => '红色', 'fr' => 'Rouge'],
                ['en' => 'Blue', 'zh' => '蓝色', 'fr' => 'Bleu'],
                ['en' => 'Black', 'zh' => '黑色', 'fr' => 'Noir'],
                ['en' => 'White', 'zh' => '白色', 'fr' => 'Blanc'],
            ];
            $colors = fake()->randomElements($colorPalette, fake()->numberBetween(2, 3));

            $options[] = [
                'name' => ['en' => 'Color', 'zh' => '颜色', 'fr' => 'Couleur'],
                'values' => $colors,
                'prices' => array_fill(0, count($colors), null), // Color alone doesn't change price.
            ];
        }

        if (empty($options)) {
            return;
        }

        // Build every combination across however many options this product got (1 or 2), so a
        // product with both Packaging and Color ends up with the full matrix, e.g. "Carton of 24 /
        // Red", "Carton of 24 / Blue", "Piece / Red", "Piece / Blue".
        $optionModels = collect($options)->map(function ($optionData, $index) use ($product) {
            $option = $product->options()->create([
                'name' => $optionData['name'],
                'sort_order' => $index,
            ]);

            $values = collect($optionData['values'])->map(fn ($valueData, $valueIndex) => [
                'model' => $option->values()->create(['value' => $valueData, 'sort_order' => $valueIndex]),
                'price' => $optionData['prices'][$valueIndex],
            ]);

            return $values;
        });

        $combinations = $optionModels->reduce(
            fn (array $carry, $values) => collect($carry)
                ->crossJoin($values)
                ->map(fn ($pair) => [...(is_array($pair[0]) ? $pair[0] : []), $pair[1]])
                ->all(),
            [[]]
        );

        foreach ($combinations as $combo) {
            // The packaging tier (if present) is the only dimension that sets an absolute price;
            // color-only combos leave price null so they inherit the base product price.
            $price = collect($combo)->pluck('price')->filter(fn ($p) => $p !== null)->first();

            $variant = $product->variants()->create([
                'sku' => $product->sku.'-'.strtoupper(Str::random(3)),
                'price' => $price,
                'stock' => fake()->numberBetween(0, max(5, (int) ($product->stock / count($combinations)))),
            ]);

            $variant->optionValues()->sync(collect($combo)->pluck('model.id')->all());
        }
    }

    private function packagingLabel(int $units): array
    {
        return match (true) {
            $units === 1 => ['en' => 'Piece', 'zh' => '单件', 'fr' => 'Pièce'],
            $units <= 12 => ['en' => "Pack of {$units}", 'zh' => "{$units}件装", 'fr' => "Paquet de {$units}"],
            $units <= 30 => ['en' => "Carton of {$units}", 'zh' => "{$units}件箱装", 'fr' => "Carton de {$units}"],
            default => ['en' => "Bale of {$units}", 'zh' => "{$units}件包装", 'fr' => "Ballot de {$units}"],
        };
    }

    /**
     * Bulk tiers apply a modest per-unit discount as quantity grows, rather than pricing linearly —
     * e.g. a Carton of 24 costs 22x a single Piece, not a flat 24x.
     */
    private function packagingPrice(int $basePriceKobo, int $units): ?int
    {
        if ($units === 1) {
            return null; // Single piece inherits the base product price exactly.
        }

        $discountFactor = match (true) {
            $units <= 12 => 0.95,
            $units <= 30 => 0.90,
            default => 0.85,
        };

        return (int) round($basePriceKobo * $units * $discountFactor);
    }
}
