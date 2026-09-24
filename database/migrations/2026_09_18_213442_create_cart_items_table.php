<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Denormalized so the cart can group line items by vendor for display without a join,
            // and so vendor deletion has a clear, explicit cascade path.
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            // Price snapshot at the moment the item was added, kobo. Re-validated against the live
            // product price at checkout time so a vendor price change can't silently overcharge/undercharge.
            $table->unsignedBigInteger('unit_price');

            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_unique_line');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
