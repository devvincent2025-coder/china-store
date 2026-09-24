<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('order_number')->unique();
            $table->string('currency', 3)->default('NGN');

            // All kobo, all sums across the order's vendor_orders — see that table's own columns
            // for the per-vendor breakdown this rolls up from.
            $table->unsignedBigInteger('original_subtotal')->default(0)->comment('Pre-discount, sum of vendor_orders.original_subtotal');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('vat_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            // Grand total actually charged: (subtotal - discount_total) + vat_total + shipping_total.
            $table->unsignedBigInteger('total');

            // One buyer-facing payment call to Korapay covers the whole order; Korapay Split Payments
            // then fans the single charge out to each vendor's subaccount plus the platform commission.
            $table->string('payment_reference')->unique();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();

            $table->json('shipping_address');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
