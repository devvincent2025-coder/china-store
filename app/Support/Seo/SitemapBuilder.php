<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\Product;
use App\Models\VendorStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds every sitemap document's XML — shared by SitemapController (served live, cached) and the
 * `sitemap:export` command (written to disk), so both always emit byte-identical output.
 *
 * "Deep" in the sense that every URL listed here is one a buyer can actually reach by browsing and
 * that Seo marks index,follow — nothing that would 404, render empty, or carry a noindex:
 *   - products: only Product::scopePubliclyListed() (same rule as Seo::productIsIndexable()), with
 *     EVERY gallery image (not just the first), so Google Images can pick up the whole gallery.
 *   - categories: only active ones whose parent (if any) is also active, and which actually have a
 *     listed product — directly, or via an active subcategory (CategoryController::show() renders
 *     both). An empty category page is thin content Google tends to flag as a soft 404.
 *   - stores: only stores with at least one publicly listed product, which by extension excludes
 *     suspended, on-vacation and pre-KYC vendors (VendorStoreController::show() renders an empty
 *     "unavailable" page for those).
 *
 * lastmod reflects when the page's VISIBLE content last changed — a category/store page changes
 * whenever a product on it does, not only when its own row is edited.
 */
class SitemapBuilder
{
    public const PRODUCTS_PER_SITEMAP = 2000;

    // Google's own cap is 1000 per URL; a product gallery past a handful adds nothing to indexing.
    private const MAX_IMAGES_PER_PRODUCT = 10;

    public function productPageCount(): int
    {
        return max(1, (int) ceil(Product::query()->publiclyListed()->count() / self::PRODUCTS_PER_SITEMAP));
    }

    /**
     * Every sitemap document, keyed by the filename its route serves it under.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        $documents = [
            'sitemap.xml' => $this->index(),
            'sitemap-static.xml' => $this->staticPages(),
            'sitemap-categories.xml' => $this->categories(),
            'sitemap-stores.xml' => $this->stores(),
        ];

        for ($page = 1; $page <= $this->productPageCount(); $page++) {
            $documents["sitemap-products-{$page}.xml"] = $this->products($page);
        }

        return $documents;
    }

    public function index(): string
    {
        $latestProduct = $this->latestProductUpdate();

        $sitemaps = [
            ['loc' => route('sitemap.static'), 'lastmod' => $latestProduct],
            ['loc' => route('sitemap.categories'), 'lastmod' => $this->max(Category::query()->where('is_active', true)->max('updated_at'), $latestProduct)],
            ['loc' => route('sitemap.stores'), 'lastmod' => $this->max(VendorStore::query()->max('updated_at'), $latestProduct)],
        ];

        $pages = $this->productPageCount();

        for ($page = 1; $page <= $pages; $page++) {
            $sitemaps[] = ['loc' => route('sitemap.products', ['page' => $page]), 'lastmod' => $latestProduct];
        }

        return view('sitemap.index', [
            'sitemaps' => array_map(fn ($s) => [...$s, 'lastmod' => $this->format($s['lastmod'])], $sitemaps),
        ])->render();
    }

    public function staticPages(): string
    {
        $latestProduct = $this->format($this->latestProductUpdate());

        // Listing pages change whenever the catalog does; legal pages carry no reliable lastmod, and
        // a made-up one is worse than none (Google stops trusting a site's lastmod values entirely).
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => $latestProduct],
            ['loc' => route('products.index'), 'priority' => '0.9', 'changefreq' => 'daily', 'lastmod' => $latestProduct],
            ['loc' => route('stores.index'), 'priority' => '0.7', 'changefreq' => 'daily'],
            ['loc' => route('kids.index'), 'priority' => '0.7', 'changefreq' => 'daily'],
            ['loc' => route('open-shop'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => route('legal.customer-protection'), 'priority' => '0.4', 'changefreq' => 'monthly'],
            ['loc' => route('legal.return-policy'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => route('legal.vendor-protection'), 'priority' => '0.3', 'changefreq' => 'monthly'],
            ['loc' => route('legal.terms'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['loc' => route('legal.privacy'), 'priority' => '0.2', 'changefreq' => 'yearly'],
        ];

        return $this->urlset($urls);
    }

    public function categories(): string
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhereHas('parent', fn ($p) => $p->where('is_active', true)))
            ->withMax(['products as latest_product_at' => fn ($q) => $q->publiclyListed()], 'updated_at')
            ->withMax(['children as latest_child_at' => fn ($q) => $q->where('is_active', true)], 'updated_at')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->get();

        $childrenByParent = $categories->whereNotNull('parent_id')->groupBy('parent_id');

        $urls = $categories
            ->filter(fn (Category $c) => $c->latest_product_at
                || $childrenByParent->get($c->id, collect())->contains(fn (Category $child) => $child->latest_product_at))
            ->map(function (Category $category) use ($childrenByParent) {
                $childProductAt = $childrenByParent->get($category->id, collect())->max('latest_product_at');

                return [
                    'loc' => route('categories.show', $category),
                    'lastmod' => $this->format($this->max($category->updated_at, $category->latest_product_at, $childProductAt, $category->latest_child_at)),
                    'priority' => $category->parent_id ? '0.6' : '0.8',
                    'changefreq' => 'daily',
                    'images' => $this->absoluteImages([$category->image_url]),
                ];
            })
            ->values()
            ->all();

        return $this->urlset($urls);
    }

    public function stores(): string
    {
        $urls = VendorStore::query()
            ->whereHas('vendor.products', fn ($q) => $q->publiclyListed())
            ->addSelect([
                'latest_product_at' => Product::query()
                    ->selectRaw('MAX(products.updated_at)')
                    ->whereColumn('products.vendor_id', 'vendor_stores.vendor_id')
                    ->where('products.status', 'published'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (VendorStore $store) => [
                'loc' => route('vendors.show', $store),
                'lastmod' => $this->format($this->max($store->updated_at, $store->latest_product_at)),
                'priority' => '0.6',
                'changefreq' => 'weekly',
                'images' => $this->absoluteImages([$store->logo_url, $store->banner_url]),
            ])
            ->all();

        return $this->urlset($urls);
    }

    public function products(int $page): string
    {
        $urls = Product::query()
            ->publiclyListed()
            ->select(['id', 'slug', 'updated_at'])
            ->with(['images' => fn ($q) => $q->select(['id', 'product_id', 'original_url', 'enhanced_url', 'sort_order'])])
            ->orderBy('id')
            ->forPage($page, self::PRODUCTS_PER_SITEMAP)
            ->get()
            ->map(fn (Product $product) => [
                'loc' => route('products.show', $product),
                'lastmod' => $this->format($product->updated_at),
                'priority' => '0.8',
                'changefreq' => 'weekly',
                'images' => $this->absoluteImages($product->images->take(self::MAX_IMAGES_PER_PRODUCT)->pluck('display_url')->all()),
            ])
            ->all();

        return $this->urlset($urls);
    }

    private function urlset(array $urls): string
    {
        foreach ($urls as &$url) {
            $url['alternates'] = Seo::hreflangUrlsForUrl($url['loc']);
        }

        return view('sitemap.urlset', ['urls' => $urls])->render();
    }

    /**
     * Image sitemap entries must be absolute URLs — a locally-stored upload's URL can be
     * root-relative (/storage/...), which Google would otherwise silently drop.
     *
     * @param  array<int, string|null>  $urls
     * @return array<int, string>
     */
    private function absoluteImages(array $urls): array
    {
        return Collection::make($urls)
            ->filter()
            ->map(fn (string $url) => preg_match('#^https?://#i', $url) ? $url : Seo::absoluteUrl($url))
            ->unique()
            ->values()
            ->all();
    }

    private function latestProductUpdate(): mixed
    {
        return Product::query()->publiclyListed()->max('updated_at');
    }

    private function max(mixed ...$dates): ?Carbon
    {
        return Collection::make($dates)
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sortDesc()
            ->first();
    }

    private function format(mixed $date): ?string
    {
        return $date ? Carbon::parse($date)->toAtomString() : null;
    }
}
