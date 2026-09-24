<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persists the ACTUAL commission % applied to this specific order at checkout time — see
        // App\Services\Commission\CommissionService. Without this, a later change to the tiers (or
        // to the vendor's own flat rate) would silently rewrite the story of what a past order
        // actually charged, since commission_amount alone doesn't say what rate produced it.
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
