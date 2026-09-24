<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * Groups published products by their (normalized) title so a buyer can see every vendor selling
 * what is effectively the same item side by side — price, the seller's rating, and their location —
 * the way a comparison-shopping page works. There's no shared "master catalog" in this marketplace
 * (each vendor lists their own Product row independently), so "the same product" is inferred by an
 * exact, case-insensitive match on the English title. This is a deliberate, simple heuristic: two
 * vendors who both typed the identical product name are treated as selling the same thing. It won't
 * catch near-duplicates with slightly different wording, but it also won't false-positive-match
 * unrelated products the way fuzzy matching could.
 */
class PriceComparisonService
{
    /**
     * @return Collection<int, array<string, mixed>> one entry per matched title group, sorted by
     *                                                 how many sellers offer it (most first)
     */
    public function searchGroups(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $lower = strtolower($term);

        $products = Product::query()
            ->publiclyListed()
            ->where(function ($query) use ($lower) {
                $query->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.zh")) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.fr")) LIKE ?', ["%{$lower}%"]);
            })
            ->with(['vendor.store', 'images', 'category', 'variants'])
            ->get();

        return $this->groupByTitle($products);
    }

    /**
     * Other sellers of the exact same product (by title), excluding the product itself — shown as
     * an "Other Sellers" section on the product page.
     *
     * @return array<int, array<string, mixed>> listings only, already sorted by price
     */
    public function otherSellersFor(Product $product): array
    {
        $title = strtolower(trim($product->getTranslation('title', 'en') ?? ''));

        if ($title === '') {
            return [];
        }

        $candidates = Product::query()
            ->publiclyListed()
            ->where('id', '!=', $product->id)
            ->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) = ?', [$title])
            ->with(['vendor.store', 'images'])
            ->get();

        return $this->buildListings($candidates);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function groupByTitle(Collection $products): Collection
    {
        return $products
            ->groupBy(fn (Product $product) => strtolower(trim($product->getTranslation('title', 'en') ?? '')))
            ->filter(fn ($group, $key) => $key !== '')
            ->map(function (Collection $group) {
                $first = $group->first();
                $listings = collect($this->buildListings($group));

                return [
                    'title' => $first->getTranslation('title', 'en'),
                    'image' => $first->images->first()?->enhanced_url ?? $first->images->first()?->original_url,
                    'category' => $first->category?->name,
                    'lowest_price' => $listings->min('price'),
                    'highest_price' => $listings->max('price'),
                    'seller_count' => $listings->count(),
                    'listings' => $listings->values()->all(),
                ];
            })
            ->sortByDesc('seller_count')
            ->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function buildListings(Collection $products): array
    {
        $ratingCache = [];
        $reviewCountCache = [];

        return $products
            ->filter(fn (Product $product) => $product->vendor !== null)
            ->map(function (Product $product) use (&$ratingCache, &$reviewCountCache) {
                $vendor = $product->vendor;

                // Cached per vendor within this one request — several products in the same group
                // often belong to the same vendor's catalog, and a vendor's rating is the same
                // number regardless of which of their products triggered the lookup.
                if (! array_key_exists($vendor->id, $ratingCache)) {
                    $ratingCache[$vendor->id] = Review::where('vendor_id', $vendor->id)->avg('rating');
                    $reviewCountCache[$vendor->id] = Review::where('vendor_id', $vendor->id)->count();
                }

                $price = $product->variants->isNotEmpty()
                    ? $product->variants->min(fn ($v) => $v->price ?? $product->price)
                    : $product->price;

                return [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'price' => $price,
                    'compare_price' => $product->compare_price,
                    'in_stock' => $product->isInStock(),
                    'vendor' => [
                        'id' => $vendor->id,
                        'business_name' => $vendor->business_name,
                        'rating' => $ratingCache[$vendor->id] ? round($ratingCache[$vendor->id], 1) : null,
                        'reviews_count' => $reviewCountCache[$vendor->id],
                        'store_slug' => $vendor->store?->slug,
                        'location' => collect([$vendor->store?->city, $vendor->store?->state])->filter()->implode(', '),
                    ],
                ];
            })
            ->sortBy('price')
            ->values()
            ->all();
    }
}
