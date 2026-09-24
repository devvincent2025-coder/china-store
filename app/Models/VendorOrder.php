<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_id',
        'coupon_id',
        'original_subtotal',
        'subtotal',
        'discount',
        'vat_amount',
        'shipping_amount',
        'total',
        'commission_amount',
        'commission_rate',
        'payout_amount',
        'fulfillment_status',
        'tracking_number',
        'tracking_carrier',
        'shipped_at',
        'delivered_at',
        'escrow_status',
        'return_window_expires_at',
        'escrow_released_at',
        'payout_reference',
        'refund_reference',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'return_window_expires_at' => 'datetime',
            'escrow_released_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Full timestamped audit trail of every fulfillment_status transition — see
     * database/migrations/..._create_vendor_order_status_histories_table.php. Ordered oldest-first
     * since that's the order the tracking stepper renders in.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(VendorOrderStatusHistory::class)->oldest();
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(VendorOrderDispute::class);
    }

    public function hasOpenDispute(): bool
    {
        return $this->disputes()->where('status', 'open')->exists();
    }

    /**
     * The buyer can confirm receipt (or the daily auto-release command can act) only once the item
     * has actually been delivered, escrow hasn't already moved past 'holding', and nothing is
     * currently in dispute — see App\Services\Escrow\EscrowService::release().
     */
    public function isEligibleForEscrowRelease(): bool
    {
        return $this->fulfillment_status === 'delivered'
            && $this->escrow_status === 'holding'
            && ! $this->hasOpenDispute();
    }
}
