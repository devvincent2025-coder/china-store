<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'original_url',
        'enhanced_url',
        'storage_path',
        'enhanced_storage_path',
        'enhanced_size_bytes',
        'processing_status',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The enhancement job runs asynchronously; fall back to the original upload until it finishes
     * so the storefront never shows a broken image while processing is in flight.
     */
    public function getDisplayUrlAttribute(): ?string
    {
        return $this->enhanced_url ?? $this->original_url;
    }
}
