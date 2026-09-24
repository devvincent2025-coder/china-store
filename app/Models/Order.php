<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'currency',
        'original_subtotal',
        'subtotal',
        'discount_total',
        'vat_total',
        'shipping_total',
        'total',
        'payment_reference',
        'payment_status',
        'paid_at',
        'shipping_address',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'user_id' => 'integer',
            'original_subtotal' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'vat_total' => 'integer',
            'shipping_total' => 'integer',
            'total' => 'integer',
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }
}
