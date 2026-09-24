<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raised by the buyer within the return window (see VendorOrder::return_window_expires_at)
        // instead of confirming receipt — an open dispute here is what blocks both the buyer's own
        // "Confirm Receipt" action and the daily auto-release command from paying the vendor out,
        // pending an admin decision (see Filament\Resources\VendorOrderDisputeResource).
        Schema::create('vendor_order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['damaged', 'wrong_item', 'not_as_described', 'missing_item', 'other']);
            $table->text('description');
            $table->enum('status', ['open', 'approved_refund', 'approved_release', 'closed'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_order_disputes');
    }
};
