<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Validates and persists a vendor's bulk-pricing tier list for either a Product (only meaningful
 * when it has no variants) or one specific ProductVariant — always wipe-and-recreate, same pattern
 * as ProductOptionSyncService, since a tier list has no identity of its own worth preserving across
 * an edit (unlike a ProductVariant, nothing downstream references a specific tier row by id).
 */
class ProductPriceTierSyncService
{
    /**
     * @param  array<int, array{min_quantity: int, max_quantity: ?int, price: float}>  $tiers
     */
    public function syncForProduct(Product $product, array $tiers): void
    {
        $this->validateTiers($tiers);

        $product->priceTiers()->delete();

        foreach ($tiers as $tier) {
            $product->priceTiers()->create($this->tierAttributes($tier));
        }
    }

    /**
     * @param  array<int, array{min_quantity: int, max_quantity: ?int, price: float}>  $tiers
     */
    public function syncForVariant(ProductVariant $variant, array $tiers): void
    {
        $this->validateTiers($tiers);

        $variant->priceTiers()->delete();

        foreach ($tiers as $tier) {
            $variant->priceTiers()->create([
                'product_id' => $variant->product_id,
                ...$this->tierAttributes($tier),
            ]);
        }
    }

    private function tierAttributes(array $tier): array
    {
        return [
            'min_quantity' => (int) $tier['min_quantity'],
            'max_quantity' => isset($tier['max_quantity']) && $tier['max_quantity'] !== '' ? (int) $tier['max_quantity'] : null,
            'price_kobo' => (int) round(((float) $tier['price']) * 100),
        ];
    }

    /**
     * Enforces: tiers don't overlap or leave a gap between them, and only the LAST tier (by
     * min_quantity) may have no upper bound — otherwise a quantity could match two tiers at once,
     * or fall through a gap and be rejected as "no matching tier" (see
     * TieredPricingService::priceForQuantity()) even though the vendor meant to cover it.
     */
    private function validateTiers(array $tiers): void
    {
        if (empty($tiers)) {
            return;
        }

        $sorted = collect($tiers)->sortBy('min_quantity')->values();

        // Starts null (not 0) — the FIRST tier's min_quantity is the product/variant's minimum
        // order quantity and may be any value >= 1 (e.g. an MOQ of 10). Only tiers AFTER the first
        // must pick up exactly where the previous one left off.
        $previousMax = null;

        foreach ($sorted as $index => $tier) {
            $min = (int) $tier['min_quantity'];
            $max = isset($tier['max_quantity']) && $tier['max_quantity'] !== '' ? (int) $tier['max_quantity'] : null;

            if ($min < 1) {
                throw ValidationException::withMessages(['price_tiers' => 'Each tier\'s minimum quantity must be at least 1.']);
            }

            if ($max !== null && $max < $min) {
                throw ValidationException::withMessages(['price_tiers' => 'A tier\'s maximum quantity must be greater than or equal to its minimum.']);
            }

            if ($previousMax !== null && $min !== $previousMax + 1) {
                throw ValidationException::withMessages([
                    'price_tiers' => 'Quantity tiers must not overlap or leave a gap — each tier should start right after the previous one ends.',
                ]);
            }

            $isLast = $index === $sorted->count() - 1;

            if (! $isLast && $max === null) {
                throw ValidationException::withMessages(['price_tiers' => 'Only the last (highest-quantity) tier may be left with no upper limit.']);
            }

            $previousMax = $max ?? PHP_INT_MAX;
        }
    }
}
