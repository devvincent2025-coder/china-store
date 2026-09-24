<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'code',
        'type',
        'value',
        'minimum_spend',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'vendor_id' => 'integer',
            'value' => 'integer',
            'minimum_spend' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Query-level equivalent of isCurrentlyValid() — used everywhere a buyer-facing page needs to
     * list "coupons you could actually use right now" (Vendors/Show.jsx's store page, Products/
     * Show.jsx) rather than checking each one in PHP after fetching every row.
     */
    public function scopeCurrentlyValid($query)
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    public function isCurrentlyValid(): bool
    {
        $now = Carbon::now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->expires_at === null || $this->expires_at->gte($now))
            && ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }

    /**
     * Computes the discount in kobo for a given subtotal, applying the minimum spend gate.
     */
    public function calculateDiscount(int $subtotalKobo): int
    {
        if ($subtotalKobo < $this->minimum_spend) {
            return 0;
        }

        return $this->type === 'percentage'
            ? intdiv($subtotalKobo * $this->value, 100)
            : min($this->value, $subtotalKobo);
    }
}
