<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per fulfillment_status transition a VendorOrder ever goes through — the columns
        // on vendor_orders itself (fulfillment_status, shipped_at, delivered_at) only ever hold the
        // CURRENT state, so this is what powers the vertical tracking stepper (buyer order page,
        // vendor order page, and the public track-order page) with a full, timestamped history.
        Schema::create('vendor_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['processing', 'shipped', 'delivered', 'cancelled']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['vendor_order_id', 'created_at']);
        });

        $this->backfillExistingVendorOrders();
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_order_status_histories');
    }

    /**
     * Every vendor_order that existed before this table was added has no history rows at all, which
     * would make the tracking stepper show a blank/"in progress" state for orders that are actually
     * long since shipped or delivered. Reconstruct a best-effort trail from the columns that already
     * existed (created_at, shipped_at, delivered_at, fulfillment_status) so old orders still render
     * a sensible timeline — this is a one-time data migration, not something new orders need since
     * they log history live going forward (see CheckoutService and Vendor\OrderController::update).
     */
    private function backfillExistingVendorOrders(): void
    {
        DB::table('vendor_orders')->orderBy('id')->chunk(500, function ($vendorOrders) {
            $rows = [];

            foreach ($vendorOrders as $vendorOrder) {
                $rows[] = [
                    'vendor_order_id' => $vendorOrder->id,
                    'status' => 'processing',
                    'note' => null,
                    'created_at' => $vendorOrder->created_at,
                    'updated_at' => $vendorOrder->created_at,
                ];

                if ($vendorOrder->shipped_at) {
                    $rows[] = [
                        'vendor_order_id' => $vendorOrder->id,
                        'status' => 'shipped',
                        'note' => null,
                        'created_at' => $vendorOrder->shipped_at,
                        'updated_at' => $vendorOrder->shipped_at,
                    ];
                }

                if ($vendorOrder->delivered_at) {
                    $rows[] = [
                        'vendor_order_id' => $vendorOrder->id,
                        'status' => 'delivered',
                        'note' => null,
                        'created_at' => $vendorOrder->delivered_at,
                        'updated_at' => $vendorOrder->delivered_at,
                    ];
                }

                if ($vendorOrder->fulfillment_status === 'cancelled') {
                    $rows[] = [
                        'vendor_order_id' => $vendorOrder->id,
                        'status' => 'cancelled',
                        'note' => null,
                        'created_at' => $vendorOrder->updated_at,
                        'updated_at' => $vendorOrder->updated_at,
                    ];
                }
            }

            if ($rows !== []) {
                DB::table('vendor_order_status_histories')->insert($rows);
            }
        });
    }
};
