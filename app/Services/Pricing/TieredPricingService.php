<?php

namespace App\Services\Pricing;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Resolves "what does one unit cost at this quantity" and "what's the minimum quantity allowed"
 * for a Product/ProductVariant pair — the read side of bulk-pricing tiers. See
 * App\Services\Product\ProductPriceTierSyncService for how a vendor's tier list is validated and
 * persisted, and App\Models\ProductPriceTier's doc comment for the schema.
 *
 * A product/variant with NO tiers configured behaves exactly as before this feature existed:
 * minOrderQuantity() is 1, priceForQuantity() is just the base price — every existing product on
 * the platform is unaffected until a vendor explicitly adds tiers.
 */
class TieredPricingService
{
    /**
     * @return Collection<int, \App\Models\ProductPriceTier>
     */
    public static function tiersFor(Product $product, ?ProductVariant $variant): Collection
    {
        if ($variant) {
            return $variant->relationLoaded('priceTiers') ? $variant->priceTiers : $variant->priceTiers()->get();
        }

        return $product->relationLoaded('priceTiers') ? $product->priceTiers : $product->priceTiers()->get();
    }

    public static function minOrderQuantity(Product $product, ?ProductVariant $variant): int
    {
        return self::tiersFor($product, $variant)->min('min_quantity') ?? 1;
    }

    /**
     * The per-unit price (kobo) a buyer pays for $quantity units, or null if $quantity doesn't
     * fall inside any configured tier (i.e. it's below the minimum order quantity, or in a gap a
     * vendor's tier list somehow left uncovered — ProductPriceTierSyncService::validateTiers()
     * should prevent gaps, but this stays defensive rather than silently mis-charging a buyer).
     */
    public static function priceForQuantity(Product $product, ?ProductVariant $variant, int $quantity): ?int
    {
        $tiers = self::tiersFor($product, $variant);

        if ($tiers->isEmpty()) {
            return $variant?->final_price ?? $product->price;
        }

        $tier = $tiers->first(
            fn ($tier) => $quantity >= $tier->min_quantity && ($tier->max_quantity === null || $quantity <= $tier->max_quantity)
        );

        return $tier?->price_kobo;
    }
}
