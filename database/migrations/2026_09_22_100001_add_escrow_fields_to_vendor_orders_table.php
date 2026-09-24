<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Escrow, Jumia-style: the buyer's payment is charged in full to the platform's own Korapay
        // balance (see CheckoutService — no split_payment at charge time any more), held here as
        // 'holding', and only disbursed to the vendor's verified bank account (see EscrowService)
        // once the return window has passed with no open dispute, or the buyer explicitly confirms
        // receipt. This is what actually implements "Jumia holds your money until delivery" — the
        // korapay_subaccount split used to release funds to vendors at charge time, which is the
        // opposite of escrow.
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->enum('escrow_status', ['holding', 'releasing', 'released', 'refund_pending', 'refunded'])
                ->default('holding')
                ->after('fulfillment_status');
            $table->timestamp('return_window_expires_at')->nullable()->after('delivered_at');
            $table->timestamp('escrow_released_at')->nullable()->after('return_window_expires_at');
            $table->string('payout_reference')->nullable()->unique()->after('escrow_released_at');
            $table->string('refund_reference')->nullable()->unique()->after('payout_reference');
        });

        // Pre-existing delivered orders (seeded/demo data from before escrow existed) would
        // otherwise never get a return_window_expires_at and so could never be auto-released —
        // backfill it from their existing delivered_at exactly like a real delivery would set it.
        DB::table('vendor_orders')
            ->where('fulfillment_status', 'delivered')
            ->whereNotNull('delivered_at')
            ->update([
                'return_window_expires_at' => DB::raw('DATE_ADD(delivered_at, INTERVAL 7 DAY)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropColumn([
                'escrow_status',
                'return_window_expires_at',
                'escrow_released_at',
                'payout_reference',
                'refund_reference',
            ]);
        });
    }
};
