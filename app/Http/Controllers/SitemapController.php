<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\VendorStore;
use App\Support\Seo\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic, DB-driven sitemap — nothing like this existed before (no package, no static file). Only
 * ever lists what Product::scopePubliclyListed() would actually show a buyer; a product that's
 * live but excluded from every listing (unverified vendor, vacation mode, etc — see
 * Seo::productIsIndexable()) has no business being offered to Google for indexing either.
 *
 * Split into an index + per-type sub-sitemaps (rather than one giant file) so the product catalog
 * can grow well past what fits in one sitemap (Google's own limit is 50k URLs / 50MB per file) —
 * see PRODUCTS_PER_SITEMAP.
 */
class SitemapController extends Controller
{
    private const PRODUCTS_PER_SITEMAP = 2000;

    private const CACHE_TTL_SECONDS = 21600; // 6 hours — a sitemap doesn't need to be second-fresh.

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.index', self::CACHE_TTL_SECONDS, function () {
            $productCount = Product::query()->publiclyListed()->count();
            $productPages = max(1, (int) ceil($productCount / self::PRODUCTS_PER_SITEMAP));

            return view('sitemap.index', [
                'staticUrl' => route('sitemap.static'),
                'categoriesUrl' => route('sitemap.categories'),
                'storesUrl' => route('sitemap.stores'),
                'productPages' => $productPages,
            ])->render();
        });

        return $this->xmlResponse($xml);
    }

    public function staticPages(): Response
    {
        $xml = Cache::remember('sitemap.static', self::CACHE_TTL_SECONDS, function () {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => route('products.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
                ['loc' => route('stores.index'), 'priority' => '0.7', 'changefreq' => 'daily'],
                ['loc' => route('kids.index'), 'priority' => '0.7', 'changefreq' => 'daily'],
                ['loc' => route('legal.return-policy'), 'priority' => '0.3', 'changefreq' => 'monthly'],
                ['loc' => route('legal.customer-protection'), 'priority' => '0.3', 'changefreq' => 'monthly'],
                ['loc' => route('legal.vendor-protection'), 'priority' => '0.3', 'changefreq' => 'monthly'],
                ['loc' => route('legal.terms'), 'priority' => '0.2', 'changefreq' => 'monthly'],
                ['loc' => route('legal.privacy'), 'priority' => '0.2', 'changefreq' => 'monthly'],
                ['loc' => route('open-shop'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ];

            foreach ($urls as &$url) {
                $url['alternates'] = Seo::hreflangUrlsForUrl($url['loc']);
            }

            return view('sitemap.urlset', ['urls' => $urls])->render();
        });

        return $this->xmlResponse($xml);
    }

    public function categories(): Response
    {
        $xml = Cache::remember('sitemap.categories', self::CACHE_TTL_SECONDS, function () {
            $urls = Category::query()
                ->where('is_active', true)
                ->get()
                ->map(function (Category $category) {
                    $loc = route('categories.show', $category);

                    return [
                        'loc' => $loc,
                        'lastmod' => $category->updated_at?->toAtomString(),
                        'priority' => '0.7',
                        'changefreq' => 'weekly',
                        'alternates' => Seo::hreflangUrlsForUrl($loc),
                    ];
                })
                ->all();

            return view('sitemap.urlset', ['urls' => $urls])->render();
        });

        return $this->xmlResponse($xml);
    }

    public function stores(): Response
    {
        $xml = Cache::remember('sitemap.stores', self::CACHE_TTL_SECONDS, function () {
            $urls = VendorStore::query()
                ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))
                ->get()
                ->map(function (VendorStore $store) {
                    $loc = route('vendors.show', $store);

                    return [
                        'loc' => $loc,
                        'lastmod' => $store->updated_at?->toAtomString(),
                        'priority' => '0.6',
                        'changefreq' => 'weekly',
                        'alternates' => Seo::hreflangUrlsForUrl($loc),
                    ];
                })
                ->all();

            return view('sitemap.urlset', ['urls' => $urls])->render();
        });

        return $this->xmlResponse($xml);
    }

    public function products(int $page): Response
    {
        $xml = Cache::remember("sitemap.products.{$page}", self::CACHE_TTL_SECONDS, function () use ($page) {
            $products = Product::query()
                ->publiclyListed()
                ->with('images')
                ->orderBy('id')
                ->forPage($page, self::PRODUCTS_PER_SITEMAP)
                ->get();

            $urls = $products->map(function (Product $product) {
                $loc = route('products.show', $product);

                return [
                    'loc' => $loc,
                    'lastmod' => $product->updated_at?->toAtomString(),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'image' => $product->images->first()?->display_url,
                    'alternates' => Seo::hreflangUrlsForUrl($loc),
                ];
            })->all();

            return view('sitemap.urlset', ['urls' => $urls])->render();
        });

        return $this->xmlResponse($xml);
    }

    private function xmlResponse(string $xml): Response
    {
        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
