<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * One concrete choice within an option, e.g. "Carton of 24" under "Packaging", or "Red" under
 * "Color".
 */
class ProductOptionValue extends Model
{
    use HasFactory, HasTranslations;

    public array $translatable = ['value'];

    protected $fillable = [
        'product_option_id',
        'value',
        'sort_order',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_value');
    }
}
