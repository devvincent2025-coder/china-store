<?php

namespace App\Services\Pricing;

use App\Models\HomeSectionItem;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-set homepage deal prices (home_section_items.deal_price) as a real sale price.
 *
 * Loads every currently-live deal in ONE query per request (the table is tiny) into a
 * product_id => lowest deal price map, so Product::$effective_price can be read for any number of
 * products without an N+1. Registered as a scoped singleton (AppServiceProvider), so each request
 * / queued job sees fresh data.
 *
 * Rules (mirrored in TieredPricingService and ProductVariant::getFinalPriceAttribute()):
 *  - a deal only ever LOWERS the price — a deal at or above the normal price is ignored;
 *  - it replaces the product's base price, so variants that inherit the base price get it too,
 *    while variants with their own explicit price (e.g. "Carton of 24") are unaffected;
 *  - with bulk tiers, the buyer pays whichever is lower: the tier price or the deal price.
 */
class DealPricing
{
    /** @var array<int, int>|null */
    private ?array $deals = null;

    public function dealPriceFor(int $productId): ?int
    {
        return $this->all()[$productId] ?? null;
    }

    /** @return array<int, int> product_id => deal price (kobo) */
    public function all(): array
    {
        if ($this->deals !== null) {
            return $this->deals;
        }

        // Before the migration has run (fresh installs, some test setups) there are no deals.
        if (! Schema::hasTable('home_section_items')) {
            return $this->deals = [];
        }

        return $this->deals = HomeSectionItem::query()
            ->live()
            ->whereNotNull('deal_price')
            ->groupBy('product_id')
            ->selectRaw('product_id, MIN(deal_price) as deal_price')
            ->pluck('deal_price', 'product_id')
            ->map(fn ($price) => (int) $price)
            ->all();
    }

    public function flush(): void
    {
        $this->deals = null;
    }
}
