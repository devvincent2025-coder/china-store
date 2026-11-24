<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'stock',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'product_id' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
        ];
    }

    // Accessors aren't included in JSON serialization unless explicitly appended — the storefront
    // (Products/Show.jsx) reads `final_price`/`min_order_quantity` directly off a serialized
    // variant, so both must be here.
    protected $appends = ['final_price', 'min_order_quantity'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_value');
    }

    /**
     * Bulk-pricing quantity breaks specific to THIS variant — a "Carton of 24" and a "Single
     * Piece" of the same product need entirely different quantity breaks, so tiers live per
     * variant rather than per product once a product has any. See
     * App\Services\Pricing\TieredPricingService.
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_quantity');
    }

    public function getMinOrderQuantityAttribute(): int
    {
        $tiers = $this->relationLoaded('priceTiers') ? $this->priceTiers : $this->priceTiers()->get();

        return $tiers->min('min_quantity') ?? 1;
    }

    /**
     * A variant's price is absolute, not a delta — a "Carton of 24" can cost many times more than
     * a single "Piece". Null means the vendor hasn't set a variant-specific price, so it inherits
     * the parent product's base price.
     */
    public function getFinalPriceAttribute(): int
    {
        // Inherits the parent's EFFECTIVE price, so a live homepage deal (DealPricing) applies to
        // variants without their own price; explicitly-priced variants are never discounted.
        return $this->price ?? $this->product->effective_price;
    }

    public function getDisplayImageAttribute(): ?string
    {
        return $this->image_url ?? $this->product->images->first()?->display_url;
    }

    /**
     * A snapshot of this variant's option/value pairs, translatable-object-shaped so the frontend
     * can render it in whatever locale it needs — e.g.
     * [{"option": {"en": "Packaging", "zh": "包装"}, "value": {"en": "Carton of 24", "zh": "24件装箱"}}].
     * Captured onto order_items.variant_label_snapshot at checkout time so the label survives the
     * vendor later editing or deleting the variant/option itself.
     */
    public function getLabelSnapshotAttribute(): array
    {
        // getTranslations() (not the magic ->name/->value accessor, which resolves to a single
        // plain string in the current request locale) — this snapshot must keep every locale so
        // order history renders correctly regardless of which locale a later viewer is in.
        return $this->optionValues->map(fn (ProductOptionValue $optionValue) => [
            'option' => $optionValue->option->getTranslations('name'),
            'value' => $optionValue->getTranslations('value'),
        ])->values()->all();
    }

    /**
     * Human-readable label in one locale, e.g. "Carton of 24 / Red" — used anywhere a plain string
     * is more convenient than the full translatable snapshot (admin tables, plain-text emails).
     */
    public function label(string $locale = 'en'): string
    {
        return $this->optionValues
            ->map(fn (ProductOptionValue $optionValue) => $optionValue->getTranslation('value', $locale))
            ->implode(' / ');
    }
}
