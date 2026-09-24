<?php

namespace App\Models;

use App\Services\Pricing\TieredPricingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'vendor_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'cart_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'vendor_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    protected $appends = ['original_unit_price', 'min_order_quantity'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lineTotal(): int
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * What this line would have cost with no discount applied — the product's compare_price where
     * one is set, falling back to its current price otherwise. A variant with its own explicit
     * price (e.g. a "Carton of 24" priced directly rather than inheriting the base product price)
     * has no discount modeled for it, so its "original" price is just its own current price —
     * fabricating a fake discount for packaging tiers would be misleading.
     */
    public function getOriginalUnitPriceAttribute(): int
    {
        if ($this->product_variant_id && $this->variant?->price !== null) {
            return $this->unit_price;
        }

        return $this->product->compare_price ?? $this->unit_price;
    }

    /**
     * The floor the quantity stepper on the Cart page must respect — a product/variant with no
     * bulk-pricing tiers configured always returns 1, so every pre-existing cart line behaves
     * exactly as before this feature existed.
     */
    public function getMinOrderQuantityAttribute(): int
    {
        return TieredPricingService::minOrderQuantity($this->product, $this->variant);
    }
}
