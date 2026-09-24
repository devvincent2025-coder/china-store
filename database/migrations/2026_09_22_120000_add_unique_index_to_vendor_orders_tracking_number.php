<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevents the same tracking number from being reused across two different orders — MySQL
        // unique indexes allow any number of NULLs to coexist, so orders with no tracking number
        // yet are unaffected. Application-level uniqueness is also enforced in
        // Vendor\OrderController::update() so this shows a friendly validation error instead of a
        // raw database exception.
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->unique('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_number']);
        });
    }
};
