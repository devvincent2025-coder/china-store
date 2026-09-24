<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the live "as you type" dropdown on both the header search box (StorefrontLayout.jsx) and
 * the Compare Prices search box (ComparePrices/Index.jsx) — see resources/js/hooks/
 * useSearchSuggestions.js. A handful of the closest-matching PUBLICLY LISTED products, not a full
 * paginated search (that's still what submitting the form itself does, via /products or /compare).
 */
class SearchSuggestionController extends Controller
{
    private const MAX_RESULTS = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->string('q'));

        // Mirrors the client's own minimum-length gate (see useSearchSuggestions.js) — a 1-character
        // query would match almost everything and isn't a useful suggestion list either way.
        if (mb_strlen($term) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $lower = mb_strtolower($term);

        $products = Product::query()
            ->publiclyListed()
            ->with(['images', 'vendor.store'])
            ->where(function ($query) use ($lower) {
                $query->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.zh")) LIKE ?', ["%{$lower}%"])
                    ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.fr")) LIKE ?', ["%{$lower}%"]);
            })
            ->orderByDesc('views_count')
            ->limit(self::MAX_RESULTS)
            ->get();

        return response()->json([
            'suggestions' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'price' => $product->price,
                'image' => $product->images->first()?->display_url,
                'store_name' => $product->vendor?->store?->name ?: $product->vendor?->business_name,
                'url' => route('products.show', $product),
            ])->values(),
        ]);
    }
}
