<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// An append-only per-vendor money trail for reconciliation — every kobo that moves in the escrow
// system (a sale credited, the platform's commission deducted, a payout sent, a refund reversed)
// gets one row here, in addition to (not instead of) the state already tracked on vendor_orders
// (escrow_status/payout_reference/refund_reference). See App\Services\Ledger\LedgerService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['sale', 'commission', 'refund', 'payout', 'adjustment']);
            // Signed kobo amount — positive increases what the platform owes the vendor (sale),
            // negative decreases it (commission, payout, refund).
            $table->bigInteger('amount');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('reference')->nullable()->unique();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
