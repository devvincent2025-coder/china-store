<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'invoice_number',
        'public_token',
        'customer_name',
        'customer_email',
        'customer_phone',
        'currency',
        'subtotal',
        'discount',
        'vat_amount',
        'shipping_amount',
        'total',
        'amount_paid',
        'status',
        'allow_partial_payment',
        'minimum_partial_amount',
        'due_date',
        'notes',
        'sent_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            // Integer casts for non-key int columns: some MySQL drivers (production's included)
            // return them as strings, which breaks strict === id checks and JSON consumers.
            'vendor_id' => 'integer',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'vat_amount' => 'integer',
            'shipping_amount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'minimum_partial_amount' => 'integer',
            'allow_partial_payment' => 'boolean',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->public_token ??= (string) Str::uuid();
            $invoice->invoice_number ??= static::generateInvoiceNumber();
        });
    }

    public static function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function isFullyPaid(): bool
    {
        return $this->amount_paid >= $this->total;
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
