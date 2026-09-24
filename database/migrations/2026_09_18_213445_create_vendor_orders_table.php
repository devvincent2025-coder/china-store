<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One order can fan out into several vendor_orders — one per vendor represented in the
        // cart — so each vendor manages fulfillment, tracking, and payout independently while the
        // buyer still sees a single order with a status per vendor.
        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();

            // What the goods would have cost with no per-item discount (sum of each line's
            // compare_price, falling back to price where no discount was set) — shown to the buyer
            // as "you would have paid X" alongside the actual discounted subtotal below.
            $table->unsignedBigInteger('original_subtotal')->default(0)->comment('Kobo, pre-discount');
            $table->unsignedBigInteger('subtotal')->comment('Kobo, before this group\'s discount');
            $table->unsignedBigInteger('discount')->default(0)->comment('Coupon discount, kobo');
            $table->unsignedBigInteger('vat_amount')->default(0)->comment('Kobo, vendor\'s own vat_rate applied to subtotal-discount');
            $table->unsignedBigInteger('shipping_amount')->default(0)->comment('Kobo, per vendor\'s shipping_fee_type/amounts');
            // Full amount the buyer pays for this vendor's portion: (subtotal - discount) + vat + shipping.
            $table->unsignedBigInteger('total')->comment('Kobo, what this vendor is owed before commission');
            // Commission is charged on goods revenue only (subtotal - discount) — never on VAT (the
            // vendor's own tax to remit) or shipping (the vendor's own fulfillment cost).
            $table->unsignedBigInteger('commission_amount')->default(0);
            $table->unsignedBigInteger('payout_amount')->comment('total - commission_amount, what Korapay routes to the vendor subaccount');

            $table->enum('fulfillment_status', ['processing', 'shipped', 'delivered', 'cancelled'])
                ->default('processing');
            $table->string('tracking_number')->nullable();
            $table->string('tracking_carrier')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_orders');
    }
};
