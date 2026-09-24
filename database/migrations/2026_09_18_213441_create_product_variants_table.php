<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku')->nullable();
            // Absolute kobo price for this specific packaging/color/etc combination — NOT a delta.
            // A "Carton of 24" can cost many times more than a single "Piece", so a delta from the
            // base product price doesn't work once packaging is a variant dimension. Null means
            // "inherit the parent product's price" (see ProductVariant::getFinalPriceAttribute()).
            $table->unsignedBigInteger('price')->nullable()->comment('Kobo, absolute. Null = inherit product price');
            $table->unsignedInteger('stock')->default(0);
            // Optional per-variant photo (e.g. a color swatch's actual product photo). Falls back to
            // the product's primary image on the storefront when null.
            $table->string('image_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
