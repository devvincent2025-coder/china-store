<?php

namespace App\Services\Escrow;

use App\Models\VendorOrder;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Services\Ledger\LedgerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The other half of the Jumia-style escrow model started in CheckoutService: that service charges
 * the buyer in full to the platform's own Korapay balance and leaves every vendor_order sitting in
 * escrow_status 'holding'. This service is what actually moves money out of escrow, in one of two
 * directions — released to the vendor's bank account, or refunded back to the buyer.
 */
class EscrowService
{
    public function __construct(
        private readonly KorapayService $korapay,
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * Pays the vendor's held-back amount into their own bank account. Callable from three places:
     * the buyer's "Confirm Receipt" button, the daily auto-release command once the return window
     * has passed, and an admin approving a dispute in the vendor's favor.
     */
    public function release(VendorOrder $vendorOrder): void
    {
        if (! $vendorOrder->isEligibleForEscrowRelease()) {
            throw ValidationException::withMessages([
                'escrow' => 'This order is not eligible for escrow release right now.',
            ]);
        }

        $vendor = $vendorOrder->vendor;

        if ($vendor->bank_verification_status !== 'verified' || blank($vendor->bank_code) || blank($vendor->account_number)) {
            throw ValidationException::withMessages([
                'escrow' => 'This vendor has no verified bank account on file — escrow cannot be released yet.',
            ]);
        }

        // The solid identity check before any money leaves the platform — see
        // Vendor::hasCompletedKyc() and Services\Vendor\VendorIdentityService. A vendor can go live
        // and sell without this, but not actually get paid until it's done.
        if (! $vendor->hasCompletedKyc()) {
            throw ValidationException::withMessages([
                'escrow' => 'This vendor has not completed identity verification (KYC) yet — escrow cannot be released until they do.',
            ]);
        }

        $reference = 'PAYOUT-'.Str::upper(Str::random(14));

        $vendorOrder->update([
            'escrow_status' => 'releasing',
            'payout_reference' => $reference,
        ]);

        $this->ledger->recordPayoutInitiated($vendorOrder, $reference);

        try {
            $this->korapay->disburseToBank(
                reference: $reference,
                amountNaira: $vendorOrder->payout_amount / 100,
                bankCode: $vendor->bank_code,
                accountNumber: $vendor->account_number,
                customerName: $vendor->resolved_account_name ?? $vendor->business_name,
                customerEmail: $vendor->contact_email,
                narration: 'Escrow payout for order '.$vendorOrder->order->order_number,
            );
        } catch (KorapayRequestException $e) {
            // Some failures (e.g. an invalid account) are rejected synchronously — there's no
            // transfer.success/failed webhook coming for those, so leaving escrow_status at
            // 'releasing' would strand this order forever. Roll back to 'holding' so it can be
            // retried, and surface a message the caller (buyer button, admin action, or the daily
            // command) can actually show, rather than a raw API error.
            $vendorOrder->update(['escrow_status' => 'holding', 'payout_reference' => null]);
            $this->ledger->markPayoutSettled($reference, succeeded: false);

            Log::error('Escrow release failed synchronously', ['vendor_order_id' => $vendorOrder->id, 'message' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'escrow' => 'The payout to this vendor could not be initiated. Their bank details may need to be re-verified.',
            ]);
        }

        // Final confirmation (escrow_status -> 'released') happens on the transfer.success webhook
        // (see KorapayWebhookController::handleTransfer()) — Korapay payouts are asynchronous, so
        // we can't mark it released just because the API call was accepted.
    }

    /**
     * Refunds all (or, for a partial dispute, part) of this vendor's share of the original charge
     * back to the buyer. Used when an admin resolves a dispute in the buyer's favor — see
     * Filament\Resources\VendorOrderDisputeResource.
     */
    public function refund(VendorOrder $vendorOrder, ?int $amountKobo = null, ?string $reason = null): void
    {
        if (in_array($vendorOrder->escrow_status, ['released', 'refunded'], true)) {
            throw ValidationException::withMessages([
                'escrow' => 'This order has already been settled and can no longer be refunded.',
            ]);
        }

        $reference = 'REFUND-'.Str::upper(Str::random(14));
        $amount = $amountKobo ?? $vendorOrder->total;

        $previousStatus = $vendorOrder->escrow_status;

        $vendorOrder->update([
            'escrow_status' => 'refund_pending',
            'refund_reference' => $reference,
        ]);

        $this->ledger->recordRefundInitiated($vendorOrder, $amount, $reference);

        try {
            $this->korapay->refundCharge(
                paymentReference: $vendorOrder->order->payment_reference,
                reference: $reference,
                amountNaira: $amount / 100,
                reason: $reason,
            );
        } catch (KorapayRequestException $e) {
            $vendorOrder->update(['escrow_status' => $previousStatus, 'refund_reference' => null]);
            $this->ledger->markRefundSettled($reference, succeeded: false);

            Log::error('Escrow refund failed synchronously', ['vendor_order_id' => $vendorOrder->id, 'message' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'escrow' => 'The refund could not be initiated. Please try again or contact Korapay support.',
            ]);
        }

        // Final confirmation (escrow_status -> 'refunded') happens on the refund.success webhook.
    }
}
