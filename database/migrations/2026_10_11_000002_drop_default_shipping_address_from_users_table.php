<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superseded by the shipping_addresses table (see the previous migration, which already copied
 * every existing default_shipping_address into it) — checkout and the profile page's default
 * address form now both read/write exclusively through ShippingAddress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('default_shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('default_shipping_address')->nullable();
        });
    }
};
