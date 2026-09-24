<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug')->unique();
            // Translatable columns (spatie/laravel-translatable) store {"en": "...", "zh": "...", "fr": "..."}.
            $table->json('name');
            $table->json('description')->nullable();

            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();

            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('NG');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_stores');
    }
};
