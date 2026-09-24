<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // "Delivers in 2-4 days" — a vendor-level estimate (not per-product/order), shown on
            // the vendor's store page, the product page, and the buyer's order detail page. Both
            // nullable together: a vendor who hasn't set this just shows no estimate, exactly as
            // today.
            $table->unsignedTinyInteger('delivery_days_min')->nullable()->after('low_stock_threshold');
            $table->unsignedTinyInteger('delivery_days_max')->nullable()->after('delivery_days_min');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['delivery_days_min', 'delivery_days_max']);
        });
    }
};
