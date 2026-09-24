<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('slug')->unique();
            $table->json('title');
            $table->json('description')->nullable();

            $table->string('sku')->nullable();
            // Base price in NGN minor units (kobo) to avoid floating point rounding on money.
            $table->unsignedBigInteger('price')->comment('Stored in kobo (NGN x 100)');
            $table->unsignedBigInteger('compare_price')->nullable()->comment('Pre-discount price, kobo');
            $table->unsignedInteger('stock')->default(0);

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
