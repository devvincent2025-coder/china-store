<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An "option" is a dimension a product varies along — e.g. "Packaging" (Piece / Pack of 6 /
     * Carton of 24 / Bale of 50) or "Color". A product can have several (Packaging AND Color at
     * once), and each concrete product_variant is one combination of one value per option — see
     * create_product_variant_option_value_table.
     */
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->json('name'); // e.g. {"en": "Packaging", "zh": "包装", "fr": "Emballage"}
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
