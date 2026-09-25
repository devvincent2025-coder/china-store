<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only product search/detail for AI agents — a JSON mirror of the storefront's
 * ProductController, reusing Product::scopePubliclyListed() so an agent can never surface (or
 * order) a product a human buyer wouldn't otherwise be able to see or buy.
 */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->publiclyListed()
            ->with(['images', 'vendor.store'])
            ->withAvg('reviews', 'rating')
            ->when($request->string('q')->toString(), function ($q) use ($request) {
                $term = strtolower($request->string('q')->toString());
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(JSON_EXTRACT(title, "$.en")) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.zh")) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(JSON_EXTRACT(title, "$.fr")) LIKE ?', ['%'.$term.'%']);
                });
            })
            ->when($request->integer('category'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
            ->when($request->filled('max_price'), fn ($q) => $q->where('price', '<=', (int) $request->input('max_price') * 100))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->orderByDesc('views_count')
            ->paginate(min((int) $request->input('per_page', 10), 25));

        return response()->json([
            'data' => $products->getCollection()->map($this->summarize(...))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->status === 'published', 404);

        $product->load(['images', 'variants.optionValues.option', 'variants.priceTiers', 'priceTiers', 'vendor.store']);
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

        return response()->json(['data' => [
            ...$this->summarize($product),
            'description' => $product->description,
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'label' => $variant->label(),
                'price_naira' => $variant->final_price / 100,
                'stock' => $variant->stock,
                'min_order_quantity' => $variant->min_order_quantity,
            ])->values(),
        ]]);
    }

    /**
     * The shape both index() and show() return per product — kept identical between the two so an
     * agent that just searched doesn't get a differently-shaped object back when it fetches detail.
     */
    private function summarize(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'title' => $product->title,
            'price_naira' => $product->price_in_naira,
            'compare_price_naira' => $product->compare_price ? $product->compare_price / 100 : null,
            'discount_percentage' => $product->discount_percentage,
            'currency' => 'NGN',
            'in_stock' => $product->isInStock(),
            'stock' => $product->stock,
            'min_order_quantity' => $product->min_order_quantity,
            'average_rating' => (float) round($product->reviews_avg_rating ?? 0, 1),
            'image_url' => $product->images->first()?->display_url,
            'vendor' => [
                'id' => $product->vendor->id,
                'store_name' => $product->vendor->store?->name,
                'store_slug' => $product->vendor->store?->slug,
            ],
            'url' => route('products.show', $product->slug),
        ];
    }
}
