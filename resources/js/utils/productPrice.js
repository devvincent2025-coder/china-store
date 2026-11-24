/**
 * Display prices for a product as serialized by App\Models\Product: `effective_price` already
 * includes a live admin-set homepage deal (App\Services\Pricing\DealPricing) and is what the cart
 * charges; `original_price` is the "was" price to strike through (vendor compare_price, or the
 * normal price while a deal is live). Falls back gracefully for payloads without the appends.
 */
export function priceOf(product) {
    return product?.effective_price ?? product?.price ?? 0;
}

export function wasPriceOf(product) {
    const now = priceOf(product);
    const was = product?.original_price ?? product?.compare_price ?? null;

    return was && was > now ? was : null;
}

export function isOnDeal(product) {
    return product?.deal_price != null;
}
