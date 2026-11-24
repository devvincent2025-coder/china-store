<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product hand-placed into a HomeSection. deal_price (kobo, optional) is a real sale price:
 * while the item and its section are live, App\Services\Pricing\DealPricing makes it the
 * product's effective price everywhere — cards, product page, cart, checkout and vendor payout.
 */
class HomeSectionItem extends Model
{
    protected $fillable = [
        'home_section_id',
        'product_id',
        'deal_price',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'home_section_id' => 'integer',
            'product_id' => 'integer',
            'deal_price' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Deal prices feed every price calculation — make sure no stale per-request map survives
        // an admin edit made in the same process (queue workers, tests).
        $flush = fn () => app(\App\Services\Pricing\DealPricing::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Item inside its own window AND its section live. */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->whereHas('section', fn ($q) => $q->live());
    }
}
