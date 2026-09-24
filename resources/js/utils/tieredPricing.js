/**
 * Client-side mirror of App\Services\Pricing\TieredPricingService — kept deliberately tiny and
 * pure so a buyer sees the live price/MOQ update instantly as they change quantity or variant,
 * without a round trip. The server (CartController, CheckoutService) is still the source of
 * truth and re-derives the same thing from the same `price_tiers` rows before anything is
 * actually charged.
 */

/**
 * @param {Array<{min_quantity:number, max_quantity:?number, price_kobo:number}>} tiers
 * @returns {number} 1 when there are no tiers — every existing product/variant's behavior.
 */
export function minOrderQuantity(tiers) {
    if (!tiers || tiers.length === 0) return 1;

    return Math.min(...tiers.map((tier) => tier.min_quantity));
}

/**
 * @param {Array<{min_quantity:number, max_quantity:?number, price_kobo:number}>} tiers
 * @param {number} quantity
 * @param {number} fallbackPriceKobo used verbatim when no tiers are configured at all.
 * @returns {?number} kobo, or null when quantity is below the MOQ / in an uncovered gap.
 */
export function resolveTierPrice(tiers, quantity, fallbackPriceKobo) {
    if (!tiers || tiers.length === 0) return fallbackPriceKobo;

    const tier = tiers.find(
        (t) => quantity >= t.min_quantity && (t.max_quantity == null || quantity <= t.max_quantity)
    );

    return tier ? tier.price_kobo : null;
}
