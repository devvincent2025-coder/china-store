<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A buyer flagging a product (counterfeit, misleading, prohibited, etc.) — reviewed by an
        // admin, who can then take the product down with a reason (see the takedown_* columns
        // added to `products` below and Filament\Resources\ProductReportResource).
        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('reason', ['counterfeit', 'prohibited', 'misleading', 'offensive', 'other']);
            $table->text('description');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('takedown_reason')->nullable()->after('status');
            $table->timestamp('taken_down_at')->nullable()->after('takedown_reason');
            $table->foreignId('taken_down_by')->nullable()->after('taken_down_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('taken_down_by');
            $table->dropColumn(['takedown_reason', 'taken_down_at']);
        });

        Schema::dropIfExists('product_reports');
    }
};
