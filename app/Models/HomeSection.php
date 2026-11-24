<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A block on the storefront homepage, rendered by Pages/Home.jsx in sort_order. Managed from
 * Filament's "Homepage sections" resource. Product-bearing types (see PRODUCT_TYPES) hold
 * hand-picked products in home_section_items, optionally with a deal price.
 */
class HomeSection extends Model
{
    use HasTranslations;

    public const TYPES = [
        'hero' => 'Hero banner + account card',
        'categories' => 'Category tiles',
        'trust' => 'Trust strip',
        'flash_deals' => 'Flash deals (with countdown)',
        'collection' => 'Collection card',
        'coupons' => 'Coupons',
        'top_picks' => 'Top Picks (sponsored stores)',
        'product_grid' => 'Product grid',
        'sell_cta' => 'Sell on CIN banner',
        'faq' => 'FAQ',
    ];

    /** Types that display products and therefore have items / deal prices. */
    public const PRODUCT_TYPES = ['flash_deals', 'collection', 'product_grid'];

    public const COLLECTION_THEMES = [
        'rose' => 'Rose (pink → peach)',
        'ink' => 'Ink (dark)',
        'mint' => 'Mint (green)',
        'sky' => 'Sky (blue)',
        'amber' => 'Amber (yellow)',
    ];

    public array $translatable = ['title', 'eyebrow', 'subtitle'];

    protected $fillable = [
        'type',
        'title',
        'eyebrow',
        'subtitle',
        'settings',
        'item_limit',
        'auto_fill',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'item_limit' => 'integer',
            'auto_fill' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Toggling/rescheduling a section changes which deal prices are live.
        $flush = fn () => app(\App\Services\Pricing\DealPricing::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function hasProducts(): bool
    {
        return in_array($this->type, self::PRODUCT_TYPES, true);
    }

    /** Active and inside its (optional) visibility window. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
