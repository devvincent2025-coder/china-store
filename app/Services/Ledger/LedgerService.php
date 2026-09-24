<?php

namespace App\Services\Ledger;

use App\Models\LedgerEntry;
use App\Models\VendorOrder;

/**
 * An append-only per-vendor money trail used for reconciliation and the admin/vendor "payouts"
 * pages — this sits alongside (never replaces) the state already tracked directly on VendorOrder
 * (escrow_status/payout_reference/refund_reference), which remains the source of truth for what
 * EscrowService itself acts on. The ledger exists purely so every kobo that ever moved can be
 * listed and audited, including its pending/success/failed lifecycle.
 */
class LedgerService
{
    /**
     * Records the two entries created the moment a buyer's charge clears: the gross amount now
     * credited to this vendor while it sits in escrow, and the platform's commission cut of it.
     * Both are 'success' immediately — the money has already been captured into the platform's own
     * Korapay balance synchronously (see CheckoutService), only its eventual payout is asynchronous.
     */
    public function recordSale(VendorOrder $vendorOrder): void
    {
        LedgerEntry::create([
            'vendor_id' => $vendorOrder->vendor_id,
            'vendor_order_id' => $vendorOrder->id,
            'type' => 'sale',
            'amount' => $vendorOrder->total,
            'status' => 'success',
            'description' => 'Order '.$vendorOrder->order->order_number,
        ]);

        if ($vendorOrder->commission_amount > 0) {
            LedgerEntry::create([
                'vendor_id' => $vendorOrder->vendor_id,
                'vendor_order_id' => $vendorOrder->id,
                'type' => 'commission',
                'amount' => -$vendorOrder->commission_amount,
                'status' => 'success',
                'description' => 'Platform commission ('.$vendorOrder->commission_rate.'%) on order '.$vendorOrder->order->order_number,
            ]);
        }
    }

    public function recordPayoutInitiated(VendorOrder $vendorOrder, string $reference): void
    {
        LedgerEntry::create([
            'vendor_id' => $vendorOrder->vendor_id,
            'vendor_order_id' => $vendorOrder->id,
            'type' => 'payout',
            'amount' => -$vendorOrder->payout_amount,
            'status' => 'pending',
            'reference' => $reference,
            'description' => 'Escrow payout for order '.$vendorOrder->order->order_number,
        ]);
    }

    public function markPayoutSettled(string $reference, bool $succeeded): void
    {
        LedgerEntry::where('reference', $reference)
            ->where('type', 'payout')
            ->update(['status' => $succeeded ? 'success' : 'failed']);
    }

    public function recordRefundInitiated(VendorOrder $vendorOrder, int $amountKobo, string $reference): void
    {
        LedgerEntry::create([
            'vendor_id' => $vendorOrder->vendor_id,
            'vendor_order_id' => $vendorOrder->id,
            'type' => 'refund',
            'amount' => -$amountKobo,
            'status' => 'pending',
            'reference' => $reference,
            'description' => 'Refund for order '.$vendorOrder->order->order_number,
            'meta' => ['amount' => $amountKobo],
        ]);
    }

    /**
     * On a confirmed refund, also reverses the vendor's share of the commission on the refunded
     * amount (prorated for partial refunds) — otherwise a fully refunded order would incorrectly
     * leave the vendor's ledger short by the commission that was never actually earned.
     */
    public function markRefundSettled(string $reference, bool $succeeded): void
    {
        $entry = LedgerEntry::where('reference', $reference)->where('type', 'refund')->first();

        if (! $entry) {
            return;
        }

        $entry->update(['status' => $succeeded ? 'success' : 'failed']);

        if (! $succeeded) {
            return;
        }

        $vendorOrder = $entry->vendorOrder;

        if (! $vendorOrder || $vendorOrder->total <= 0 || $vendorOrder->commission_amount <= 0) {
            return;
        }

        $refundedAmount = (int) ($entry->meta['amount'] ?? abs($entry->amount));
        $commissionReversal = intdiv($vendorOrder->commission_amount * $refundedAmount, $vendorOrder->total);

        if ($commissionReversal <= 0) {
            return;
        }

        LedgerEntry::create([
            'vendor_id' => $entry->vendor_id,
            'vendor_order_id' => $entry->vendor_order_id,
            'type' => 'adjustment',
            'amount' => $commissionReversal,
            'status' => 'success',
            'description' => 'Commission reversed for refund on order '.$vendorOrder->order->order_number,
        ]);
    }
}
