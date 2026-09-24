<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOrderDispute extends Model
{
    use HasFactory;

    /**
     * How long a vendor has to respond with evidence before an admin can step in and resolve the
     * dispute regardless — see the buyer's brief: "Vendor gets 48-72 hours to respond."
     */
    public const RESPONSE_WINDOW_HOURS = 72;

    protected $fillable = [
        'vendor_order_id',
        'reason',
        'description',
        'evidence',
        'status',
        'vendor_response_deadline',
        'vendor_response',
        'vendor_evidence',
        'vendor_responded_at',
        'resolution_note',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'vendor_evidence' => 'array',
            'vendor_response_deadline' => 'datetime',
            'vendor_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function hasVendorResponded(): bool
    {
        return $this->vendor_responded_at !== null;
    }

    public function vendorResponseOverdue(): bool
    {
        return $this->isOpen()
            && ! $this->hasVendorResponded()
            && $this->vendor_response_deadline !== null
            && $this->vendor_response_deadline->isPast();
    }
}
