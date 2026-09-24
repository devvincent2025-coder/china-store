<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\VendorStore;
use App\Services\Ads\AdCampaignService;
use App\Services\Cart\CartResolver;
use App\Services\Product\PriceComparisonService;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, AdCampaignService $ads): Response
    {
        $visualIds = $request->boolean('visual')
            ? collect($request->session()->get('visual_search_ids', []))->filter()->values()->all()
            : [];

        $priceBounds = Product::query()
            ->publiclyListed()
            ->selectRaw('COALESCE(MIN(price), 0) as min_kobo, COALESCE(MAX(price), 0) as max_kobo')
            ->first();

        $minBoundNaira = (int) floor(($priceBounds->min_kobo ?? 0) / 100);
        $maxBoundNaira = (int) max($minBoundNaira + 100, ceil(($priceBounds->max_kobo ?? 0) / 100));

        $products = Product::query()
            ->publiclyListed()
            ->with(['images', 'vendor.store'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($visualIds !== [], function ($q) use ($visualIds) {
                $q->whereIn('id', $visualIds)
                    ->orderByRaw('FIELD(id,'.implode(',', array_map('intval', $visualIds)).')');
            })
            ->when($visualIds === [] && $request->string('q')->toString(), function ($q) use ($request) {
                $term = strtolower($request->string('q')->toString());
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.zh")) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.fr")) LIKE ?', ['%'.$term.'%']);
                });
            })
            ->when($request->integer('category'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->filled('min_price'), function ($q) use ($request) {
                $q->where('price', '>=', (int) $request->input('min_price') * 100);
            })
            ->when($request->filled('max_price'), function ($q) use ($request) {
                $q->where('price', '<=', (int) $request->input('max_price') * 100);
            })
            ->when($request->integer('store'), function ($q, $storeId) {
                $q->whereHas('vendor.store', fn ($s) => $s->where('id', $storeId));
            })
            ->when($request->integer('min_rating'), function ($q) use ($request) {
                $rating = (int) $request->input('min_rating');
                $q->whereHas('reviews')
                    ->whereRaw(
                        '(select avg(rating) from reviews where reviews.product_id = products.id) >= ?',
                        [$rating]
                    );
            })
            ->when($request->boolean('on_sale'), function ($q) {
                $q->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price');
            })
            ->when($request->integer('min_discount'), function ($q) use ($request) {
                $pct = (int) $request->input('min_discount');
                $q->whereNotNull('compare_price')
                    ->whereColumn('compare_price', '>', 'price')
                    ->whereRaw('((compare_price - price) / compare_price) * 100 >= ?', [$pct]);
            })
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->boolean('verified'), function ($q) {
                $q->whereHas('vendor', fn ($v) => $v->where('documents_status', 'approved'));
            })
            ->when($request->string('city')->toString(), function ($q) use ($request) {
                $city = $request->string('city')->toString();
                $q->whereHas('vendor.store', fn ($s) => $s->where('city', $city));
            })
            ->when($visualIds === [], function ($q) use ($request) {
                match ($request->string('sort')->toString()) {
                    'price_asc' => $q->orderBy('price', 'asc'),
                    'price_desc' => $q->orderBy('price', 'desc'),
                    'newest' => $q->orderByDesc('created_at'),
                    'rating' => $q->orderByDesc('reviews_avg_rating'),
                    default => $q->orderByDesc('views_count'),
                };
            })
            ->paginate(24)
            ->withQueryString();

        $stores = VendorStore::query()
            ->whereHas('vendor.products', fn ($q) => $q->publiclyListed())
            ->with('vendor:id,business_name,documents_status')
            ->orderBy('id')
            ->get(['id', 'vendor_id', 'slug', 'name', 'city', 'state']);

        $cities = VendorStore::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->whereHas('vendor.products', fn ($q) => $q->publiclyListed())
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->values();

        $topPicks = $ads->activeForDisplay(5);
        $ads->recordImpressions($topPicks);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'topPicks' => $topPicks->map(fn ($campaign) => [
                'token' => $campaign->token,
                'banner_url' => $campaign->banner_url,
                'store' => $campaign->vendor->store,
            ]),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'stores' => $stores,
            'cities' => $cities,
            'priceBounds' => [
                'min' => $minBoundNaira,
                'max' => $maxBoundNaira,
            ],
            'filters' => array_merge(
                $request->only([
                    'q', 'category', 'min_price', 'max_price', 'sort',
                    'store', 'min_rating', 'min_discount', 'city',
                ]),
                [
                    'visual' => $request->boolean('visual'),
                    'on_sale' => $request->boolean('on_sale'),
                    'in_stock' => $request->boolean('in_stock'),
                    'featured' => $request->boolean('featured'),
                    'verified' => $request->boolean('verified'),
                ],
            ),
            'seo' => Seo::forProductsIndex($request),
        ]);
    }

    public function show(Request $request, Product $product, PriceComparisonService $comparisons, CartResolver $carts): Response
    {
        abort_unless($product->status === 'published', 404);

        $product->increment('views_count');
        $product->load(['images', 'variants.optionValues.option', 'variants.priceTiers', 'options.values', 'vendor.store', 'category', 'reviews.user', 'priceTiers']);

        // Avoids an N+1 query per variant: ProductVariant::getFinalPriceAttribute() falls back to
        // $this->product->price when the variant has no price of its own, which would otherwise
        // lazy-load the parent product fresh for every variant during JSON serialization below.
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

        $relatedProducts = Product::query()
            ->publiclyListed()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('images')
            ->limit(8)
            ->get();

        return Inertia::render('Products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            // Other vendors selling what appears to be this same product — see
            // PriceComparisonService's doc comment for how "the same product" is matched.
            'otherSellers' => $comparisons->otherSellersFor($product),
            // This vendor's currently-redeemable coupons — previously invisible anywhere except
            // the checkout coupon box itself (if a buyer already knew the code).
            'coupons' => $product->vendor->coupons()->currentlyValid()->get(['code', 'type', 'value', 'minimum_spend']),
            // This product's lines already in the buyer's cart (one per variant), so the page can
            // swap "Add to Cart" for a stepper that edits the existing line in place.
            'cartLines' => $carts->existing($request)?->items()
                ->where('product_id', $product->id)
                ->get(['id', 'product_variant_id', 'quantity'])
                // only(), not toArray(): CartItem's appended price attributes need columns (and
                // relations) this page doesn't load or use.
                ->map->only(['id', 'product_variant_id', 'quantity'])
                ->values() ?? [],
            'seo' => Seo::forProduct($request, $product),
        ]);
    }
}
