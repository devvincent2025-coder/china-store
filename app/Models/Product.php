<?php

namespace App\Models;

use App\Services\Pricing\DealPricing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'vendor_id',
        'category_id',
        'slug',
        'title',
        'description',
        'sku',
        'price',
        'compare_price',
        'stock',
        'status',
        'is_featured',
        'views_count',
        'takedown_reason',
        'taken_down_at',
        'taken_down_by',
        'flagged_at',
        'flagged_reason',
    ];

    protected $appends = ['discount_percentage', 'min_order_quantity', 'deal_price', 'effective_price', 'original_price'];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'vendor_id' => 'integer',
            'category_id' => 'integer',
            'price' => 'integer',
            'compare_price' => 'integer',
            'stock' => 'integer',
            'taken_down_by' => 'integer',
            'views_count' => 'integer',
            'is_featured' => 'boolean',
            'taken_down_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductReport::class);
    }

    public function takenDownBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_down_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Every buyer-facing product listing (home, category, search, image search, compare prices)
     * should use this instead of a bare where('status', 'published') — it also excludes products
     * from a vendor currently in vacation mode (Vendor::vacation_mode, a voluntary, reversible
     * pause), from a vendor the platform has suspended/rejected (see
     * Services\Vendor\VendorSuspensionService — an admin-only, investigated decision, not
     * reversible by the vendor themselves), and from a vendor who hasn't completed KYC yet (see
     * Vendor::hasCompletedKyc()) — a vendor can create and publish products before finishing KYC,
     * they just don't reach buyers until then, matching how a payout is likewise held until KYC
     * clears (EscrowService::release()). A vendor's own dashboard/product-management views
     * intentionally do NOT use this scope, since a vendor must still see and manage their own
     * products while on vacation or mid-KYC (though a suspended vendor can't reach their dashboard
     * at all — see Http\Middleware\EnsureVendorIsActive).
     */
    public function scopePubliclyListed($query)
    {
        return $query->where('status', 'published')
            ->whereHas('vendor', function ($v) {
                $v->where('vacation_mode', false)
                    ->where('status', 'active')
                    ->where('identity_verification_status', 'approved')
                    ->whereNotNull('phone_verified_at')
                    ->whereHas('user', fn ($u) => $u->whereNotNull('email_verified_at'));
            });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasMany
    {
        return $this->images()->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * The dimensions this product varies along (e.g. Packaging, Color), in vendor-defined order.
     * Empty for a simple product with no packaging/color/etc choices — those are sold directly off
     * the base product's price/stock with no variant selection required.
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class);
    }

    /**
     * Bulk-pricing quantity breaks for this PRODUCT — only meaningful (and only ever shown/used)
     * when the product has no options/variants of its own; a product with variants keeps its tiers
     * on each ProductVariant instead, the same way variant price/stock already override the base
     * product's. See App\Services\Pricing\TieredPricingService.
     */
    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->whereNull('product_variant_id')->orderBy('min_quantity');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Price stored in kobo; exposed in naira for the frontend/admin so nobody has to remember to
     * divide by 100 at every call site.
     */
    public function getPriceInNairaAttribute(): float
    {
        return $this->price / 100;
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

    /**
     * Whole-number percent off, e.g. 20 for "-20%" — 0 when there's no discount (no compare_price,
     * or compare_price isn't actually higher than price). Appended so the storefront's discount
     * badge (product cards, product detail page) never has to re-derive this itself.
     */
    public function getDiscountPercentageAttribute(): int
    {
        $reference = $this->original_price;

        if (! $reference || $reference <= $this->effective_price) {
            return 0;
        }

        return (int) round((($reference - $this->effective_price) / $reference) * 100);
    }

    /**
     * Admin-set homepage deal price (kobo) currently live for this product, or null — only when
     * it's actually below the normal price. See App\Services\Pricing\DealPricing.
     */
    public function getDealPriceAttribute(): ?int
    {
        $deal = app(DealPricing::class)->dealPriceFor((int) $this->id);

        return $deal !== null && $deal < $this->price ? $deal : null;
    }

    /** What a buyer pays per unit right now (before bulk tiers): the deal price, else price. */
    public function getEffectivePriceAttribute(): int
    {
        return $this->deal_price ?? (int) $this->price;
    }

    /**
     * The "was" price to strike through next to effective_price: the vendor's compare_price when
     * higher, otherwise the normal price while a deal is live. Null when there's no discount.
     */
    public function getOriginalPriceAttribute(): ?int
    {
        $candidates = array_filter([
            $this->compare_price,
            $this->deal_price !== null ? (int) $this->price : null,
        ]);

        $original = $candidates ? max($candidates) : null;

        return $original !== null && $original > $this->effective_price ? (int) $original : null;
    }

    /**
     * 1 unless bulk-pricing tiers are configured directly on this product (never the case for a
     * product WITH variants — each variant has its own MOQ instead, see
     * ProductVariant::getMinOrderQuantityAttribute()).
     */
    public function getMinOrderQuantityAttribute(): int
    {
        $tiers = $this->relationLoaded('priceTiers') ? $this->priceTiers : $this->priceTiers()->get();

        return $tiers->min('min_quantity') ?? 1;
    }

    public function isInStock(): bool
    {
        return $this->variants->isNotEmpty()
            ? $this->variants->sum('stock') > 0
            : $this->stock > 0;
    }
}
