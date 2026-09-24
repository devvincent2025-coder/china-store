<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            // Null vendor_id = platform-wide coupon created by an admin in Filament.
            $table->foreignId('vendor_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'flat']);
            // Percentage: whole number 1-100. Flat: kobo amount.
            $table->unsignedInteger('value');
            $table->unsignedBigInteger('minimum_spend')->default(0)->comment('Kobo');

            // A coupon with no linked products applies to every product in the owning vendor's store
            // (or platform-wide, if vendor_id is null too). Linked products scope it further.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
