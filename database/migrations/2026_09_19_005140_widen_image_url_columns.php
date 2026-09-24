<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
        });

        Schema::table('vendor_stores', function (Blueprint $table) {
            $table->text('logo_url')->nullable()->change();
            $table->text('banner_url')->nullable()->change();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->text('original_url')->change();
            $table->text('enhanced_url')->nullable()->change();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->text('image_url')->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
        });

        // Missed in the first pass: real image URLs (e.g. Wikimedia's long thumbnail paths used by
        // the seeder, or a future CDN's signed URLs) routinely exceed 255 chars, so this snapshot
        // silently failed to insert for any order whose product had a long image URL.
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('product_image_snapshot')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });

        Schema::table('vendor_stores', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->change();
            $table->string('banner_url')->nullable()->change();
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->string('original_url')->change();
            $table->string('enhanced_url')->nullable()->change();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('image_url')->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('image_url')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_image_snapshot')->nullable()->change();
        });
    }
};
