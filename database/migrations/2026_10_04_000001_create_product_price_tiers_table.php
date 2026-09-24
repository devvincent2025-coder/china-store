<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Null = this tier belongs to the base Product (only meaningful for a product with no
            // options/variants). Set = this tier belongs to one specific ProductVariant instead —
            // a "Carton of 24" and a "Single Piece" variant of the same product need entirely
            // different quantity breaks. See App\Services\Pricing\TieredPricingService for how a
            // buyer's chosen quantity resolves to one of these rows, and
            // App\Services\Product\ProductOptionSyncService's doc comment for why this must
            // cascade-delete: a renamed option value deletes and recreates its ProductVariant row.
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('min_quantity');

            // Null = no upper bound — only the LAST tier (by min_quantity) for a given
            // product/variant may leave this null; enforced in
            // ProductPriceTierSyncService::validateTiers(), not at the DB layer.
            $table->unsignedInteger('max_quantity')->nullable();

            // Absolute per-unit price at this quantity break, in kobo — same "absolute, not a
            // delta" convention as ProductVariant::price.
            $table->unsignedBigInteger('price_kobo');

            $table->timestamps();

            $table->index(['product_id', 'product_variant_id', 'min_quantity'], 'price_tiers_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_tiers');
    }
};
