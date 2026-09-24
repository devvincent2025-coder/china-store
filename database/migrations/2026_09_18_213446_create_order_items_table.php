<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnDelete();
            // Kept nullable-on-delete rather than cascading: a vendor deleting a product later must
            // not erase historical order line items.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot the product's details at time of purchase so historical orders stay accurate
            // even if the vendor edits or deletes the product afterward.
            $table->json('product_title_snapshot');
            $table->string('product_image_snapshot')->nullable();
            // Snapshot of the selected variant's option/value pairs, e.g.
            // [{"option": {"en": "Packaging", ...}, "value": {"en": "Carton of 24", ...}}, ...].
            // Kept independently of product_variant_id since a variant (or its option values) can
            // be edited or deleted by the vendor after the order was placed.
            $table->json('variant_label_snapshot')->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price')->comment('Kobo, at time of purchase');
            $table->unsignedBigInteger('line_total')->comment('unit_price * quantity, kobo');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
