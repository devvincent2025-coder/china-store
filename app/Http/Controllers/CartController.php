<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartResolver;
use App\Services\Pricing\TieredPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartResolver $carts)
    {
    }

    public function index(Request $request): Response
    {
        $cart = $this->carts->resolve($request);

        return Inertia::render('Cart/Index', [
            'itemsByVendor' => $cart->itemsGroupedByVendor()->map(fn ($items) => [
                'vendor' => $items->first()->vendor->load('store'),
                'items' => $items,
                'original_subtotal' => $items->sum(fn ($item) => $item->original_unit_price * $item->quantity),
                'subtotal' => $items->sum(fn ($item) => $item->unit_price * $item->quantity),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            // A 404 like the product check above, not a bare 422 — a 422 HttpException isn't a
            // validation error Inertia can show inline; it renders as a full-page error dialog.
            abort_unless($variant->product_id === $product->id, 404);
        }

        // Quick-add from a product card can't pick a size/colour — send the buyer to the product
        // page to choose, rather than adding a variant-less line for a product that has options.
        if (! $variant && $product->options()->exists()) {
            return redirect()
                ->route('products.show', $product->slug)
                ->with('warning', __('Please choose your options before adding this item to your cart.'));
        }

        $cart = $this->carts->resolve($request);

        $item = $cart->items()->firstOrNew([
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'] ?? null,
        ]);

        // Add-to-cart merges with whatever's already on this line (see the $item->quantity
        // assignment below), so the tier/MOQ check must run against the RESULTING quantity, not
        // just the amount being added this time.
        $resultingQuantity = ($item->exists ? $item->quantity : 0) + $validated['quantity'];
        $this->assertWithinStock($product, $variant, $resultingQuantity);
        $unitPrice = $this->resolveTieredPrice($product, $variant, $resultingQuantity);

        $item->vendor_id = $product->vendor_id;
        $item->unit_price = $unitPrice;
        $item->quantity = $resultingQuantity;
        $item->save();

        return back()->with('success', 'Added to cart.');
    }

    public function update(Request $request, int $cartItem): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $item = $this->carts->resolve($request)->items()->with(['product.priceTiers', 'variant.priceTiers'])->findOrFail($cartItem);

        // Unlike store() above, this previously never recomputed unit_price at all — a buyer
        // increasing quantity here would still be charged whatever tier/price applied when the
        // line was first added, even after crossing into a cheaper (or now-invalid) bulk-pricing
        // tier. Recomputing here keeps the cart's displayed price honest between add-to-cart and
        // checkout (CheckoutService re-does this same check as a final safety net).
        $this->assertWithinStock($item->product, $item->variant, $validated['quantity']);
        $unitPrice = $this->resolveTieredPrice($item->product, $item->variant, $validated['quantity']);

        $item->update(['quantity' => $validated['quantity'], 'unit_price' => $unitPrice]);

        return back()->with('success', 'Cart updated.');
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

        // Only possible if a vendor's tier list somehow has a gap above the MOQ — defensive, since
        // ProductPriceTierSyncService::validateTiers() should prevent this at write time.
        if ($unitPrice === null) {
            throw ValidationException::withMessages([
                'quantity' => __('This quantity is not available for this item.'),
            ]);
        }

        return $unitPrice;
    }

    /**
     * Caps a cart line at what's in stock right now. Only a soft guard — stock can still sell out
     * between here and payment, which CheckoutService::assertStockAvailable() re-checks under lock.
     */
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

    public function destroy(Request $request, int $cartItem): RedirectResponse
    {
        $this->carts->resolve($request)->items()->findOrFail($cartItem)->delete();

        return back()->with('success', 'Item removed from cart.');
    }
}
