<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One quantity-based price break for a Product or (more commonly) one specific ProductVariant —
 * "buy 10-49 at ₦900/unit, 50+ at ₦800/unit". See App\Services\Pricing\TieredPricingService for
 * how a buyer's chosen quantity resolves to one of these rows, and
 * App\Services\Product\ProductPriceTierSyncService for how a vendor's tier list is validated and
 * persisted (always wipe-and-recreate per product/variant, same pattern as ProductOptionSyncService).
 */
class ProductPriceTier extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'min_quantity',
        'max_quantity',
        'price_kobo',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'price_kobo' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
