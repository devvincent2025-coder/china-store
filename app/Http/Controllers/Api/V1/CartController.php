<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartResolver;
use App\Services\Pricing\TieredPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * JSON mirror of Http\Controllers\CartController for AI agents — same tier/stock/MOQ rules
 * (TieredPricingService, CartResolver), just responding with the cart instead of an Inertia
 * redirect. Always resolves the token owner's own cart (CartResolver::resolve() falls back to
 * $request->user()->cart() whenever a user is authenticated), never a guest cart, since every
 * route here sits behind auth:sanctum.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartResolver $carts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->present($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        abort_unless($product->status === 'published' && ! $product->vendor->vacation_mode, 404);

        $variant = null;
        if ($validated['product_variant_id'] ?? null) {
            $variant = ProductVariant::findOrFail($validated['product_variant_id']);
            abort_unless($variant->product_id === $product->id, 404);
        }

        $cart = $this->carts->resolve($request);

        $item = $cart->items()->firstOrNew([
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'] ?? null,
        ]);

        $resultingQuantity = ($item->exists ? $item->quantity : 0) + $validated['quantity'];
        $this->assertWithinStock($product, $variant, $resultingQuantity);
        $unitPrice = $this->resolveTieredPrice($product, $variant, $resultingQuantity);

        $item->vendor_id = $product->vendor_id;
        $item->unit_price = $unitPrice;
        $item->quantity = $resultingQuantity;
        $item->save();

        return response()->json(['data' => $this->present($request)], 201);
    }

    public function update(Request $request, int $cartItem): JsonResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $item = $this->carts->resolve($request)->items()->with(['product.priceTiers', 'variant.priceTiers'])->findOrFail($cartItem);

        $this->assertWithinStock($item->product, $item->variant, $validated['quantity']);
        $unitPrice = $this->resolveTieredPrice($item->product, $item->variant, $validated['quantity']);

        $item->update(['quantity' => $validated['quantity'], 'unit_price' => $unitPrice]);

        return response()->json(['data' => $this->present($request)]);
    }

    public function destroy(Request $request, int $cartItem): JsonResponse
    {
        $this->carts->resolve($request)->items()->findOrFail($cartItem)->delete();

        return response()->json(['data' => $this->present($request)]);
    }

    private function present(Request $request): array
    {
        $cart = $this->carts->resolve($request)->load(['items.product.images', 'items.variant']);

        $items = $cart->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'title' => $item->product->title,
            'variant_label' => $item->variant?->label(),
            'quantity' => $item->quantity,
            'unit_price_naira' => $item->unit_price / 100,
            'line_total_naira' => $item->lineTotal() / 100,
        ]);

        return [
            'items' => $items->values(),
            'subtotal_naira' => $items->sum('line_total_naira'),
            'currency' => 'NGN',
        ];
    }

    private function resolveTieredPrice(Product $product, ?ProductVariant $variant, int $quantity): int
    {
        $moq = TieredPricingService::minOrderQuantity($product, $variant);

        if ($quantity < $moq) {
            throw ValidationException::withMessages([
                'quantity' => "The minimum order quantity for this item is {$moq}.",
            ]);
        }

        $unitPrice = TieredPricingService::priceForQuantity($product, $variant, $quantity);

        if ($unitPrice === null) {
            throw ValidationException::withMessages([
                'quantity' => __('This quantity is not available for this item.'),
            ]);
        }

        return $unitPrice;
    }

    private function assertWithinStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $available = $variant ? $variant->stock : $product->stock;

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => $available <= 0
                    ? __('This item is out of stock.')
                    : __('Only :count left in stock.', ['count' => $available]),
            ]);
        }
    }
}
