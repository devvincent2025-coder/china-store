<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'guest_token'];

    // Not user-facing data — this app never has a reason to serialize a bare Cart model to the
    // frontend (CartController::index() always sends a derived itemsByVendor shape instead), but
    // hidden here too as a defensive default in case that ever changes.
    protected $hidden = ['guest_token'];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Groups line items by vendor so the cart/checkout UI can render one shipping block per vendor,
     * matching the "multiple vendors in one cart" requirement.
     */
    public function itemsGroupedByVendor(): Collection
    {
        return $this->items()
            ->with(['product.images', 'product.priceTiers', 'variant.optionValues.option', 'variant.priceTiers', 'vendor.store'])
            ->get()
            ->groupBy('vendor_id');
    }

    public function subtotal(): int
    {
        return $this->items->sum(fn (CartItem $item) => $item->unit_price * $item->quantity);
    }
}
