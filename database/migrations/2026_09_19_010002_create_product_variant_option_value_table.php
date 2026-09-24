<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pins one product_variant to the exact combination of option values it represents — e.g. the
     * variant priced/stocked as "Carton of 24, Red" links to both the "Carton of 24" (Packaging)
     * and "Red" (Color) option-value rows. A buyer picking one value per option on the storefront
     * resolves to exactly one variant via this pivot (see App\Services\Product\VariantResolver, or
     * the equivalent client-side matching logic).
     */
    public function up(): void
    {
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();
            $table->unique(['product_variant_id', 'product_option_value_id'], 'variant_option_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
    }
};
