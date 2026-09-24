<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\VendorOrder;
use App\Notifications\EscrowReleased;
use App\Notifications\OrderRefunded;
use App\Services\Ads\AdCampaignService;
use App\Services\Checkout\CheckoutService;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Ledger\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class KorapayWebhookController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AdCampaignService $ads,
        private readonly CheckoutService $checkout,
        private readonly InvoicePaymentService $invoicePayments,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('Korapay webhook: invalid signature');

            return response()->noContent(401);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);
        $reference = $data['reference'] ?? null;

        return match (true) {
            str_starts_with((string) $event, 'transfer.') => $this->handleTransfer($event, $reference),
            str_starts_with((string) $event, 'refund.') => $this->handleRefund($event, $reference),
            default => $this->handleCharge($event, $reference),
        };
    }

    private function handleCharge(?string $event, ?string $reference): Response
    {
        $order = Order::where('payment_reference', $reference)->first();

        if ($order) {
            return $this->handleOrderCharge($event, $order);
        }

        $payment = InvoicePayment::where('reference', $reference)->first();

        if ($payment) {
            return $this->handleInvoicePayment($event, $payment);
        }

        $adCampaign = AdCampaign::where('payment_reference', $reference)->first();

        if ($adCampaign) {
            return $this->handleAdCampaignCharge($event, $adCampaign);
        }

        return response()->noContent(200); // Unrelated event — acknowledge and move on.
    }

    /**
     * Confirms an escrow payout to a vendor's bank account — see EscrowService::release(), which
     * only sets escrow_status to 'releasing' since the transfer itself is asynchronous.
     */
    private function handleTransfer(?string $event, ?string $reference): Response
    {
        $vendorOrder = VendorOrder::where('payout_reference', $reference)->first();

        if (! $vendorOrder) {
            return response()->noContent(200);
        }

        if ($event === 'transfer.success' && $vendorOrder->escrow_status !== 'released') {
            $vendorOrder->update(['escrow_status' => 'released', 'escrow_released_at' => now()]);
            $this->ledger->markPayoutSettled($reference, succeeded: true);
            $vendorOrder->vendor->user->notify(new EscrowReleased($vendorOrder));
        } elseif ($event === 'transfer.failed') {
            // Revert to 'holding' so the payout can be retried (manually or by the next scheduled
            // run) rather than getting permanently stuck in 'releasing'.
            Log::error('Korapay: escrow payout transfer failed', ['vendor_order_id' => $vendorOrder->id, 'reference' => $reference]);
            $vendorOrder->update(['escrow_status' => 'holding']);
            $this->ledger->markPayoutSettled($reference, succeeded: false);
        }

        return response()->noContent(200);
    }

    /**
     * Confirms a dispute-driven refund back to the buyer — see EscrowService::refund().
     */
    private function handleRefund(?string $event, ?string $reference): Response
    {
        $vendorOrder = VendorOrder::where('refund_reference', $reference)->first();

        if (! $vendorOrder) {
            return response()->noContent(200);
        }

        if ($event === 'refund.success' && $vendorOrder->escrow_status !== 'refunded') {
            $vendorOrder->update(['escrow_status' => 'refunded']);
            $this->ledger->markRefundSettled($reference, succeeded: true);
            $vendorOrder->order->user->notify(new OrderRefunded($vendorOrder));
        } elseif ($event === 'refund.failed') {
            Log::error('Korapay: escrow refund failed', ['vendor_order_id' => $vendorOrder->id, 'reference' => $reference]);
            $vendorOrder->update(['escrow_status' => 'holding']);
            $this->ledger->markRefundSettled($reference, succeeded: false);
        }

        return response()->noContent(200);
    }

    private function handleOrderCharge(?string $event, Order $order): Response
    {
        if ($event === 'charge.success') {
            // Delegates to CheckoutService::confirmPaid(), which CheckoutController::callback()
            // also calls as a synchronous fallback — both are idempotent against payment_status.
            $this->checkout->confirmPaid($order);
        } elseif ($event === 'charge.failed') {
            $this->checkout->confirmFailed($order);
        }

        return response()->noContent(200);
    }

    /**
     * Trusts only the amount WE recorded on the InvoicePayment row when it was initialized (not
     * whatever the webhook payload claims), the same way handleOrderCharge() above never re-derives
     * the order total from webhook data — the webhook is purely a "this reference cleared" signal.
     */
    private function handleInvoicePayment(?string $event, InvoicePayment $payment): Response
    {
        if ($event === 'charge.success') {
            $this->invoicePayments->confirmPayment($payment);
        } elseif ($event === 'charge.failed' && $payment->status !== 'success') {
            $payment->update(['status' => 'failed']);
        }

        return response()->noContent(200);
    }

    /**
     * Confirms a vendor's "boost your store" ad payment — see AdCampaignService::markPaid(),
     * which moves the campaign to pending_approval (still not shown to buyers until an admin
     * approves it in Filament).
     */
    private function handleAdCampaignCharge(?string $event, AdCampaign $adCampaign): Response
    {
        if ($event === 'charge.success' && $adCampaign->status === 'pending_payment') {
            $this->ads->markPaid($adCampaign);
        } elseif ($event === 'charge.failed') {
            Log::warning('Korapay: ad campaign payment failed', ['ad_campaign_id' => $adCampaign->id]);
        }

        return response()->noContent(200);
    }

    /**
     * Korapay signs webhook payloads with the merchant secret key so we can trust the event came
     * from them and not a spoofed request — never process a payment confirmation without this check.
     */
    private function hasValidSignature(Request $request): bool
    {
        $signature = $request->header('x-korapay-signature');

        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', json_encode($request->input('data', [])), config('services.korapay.secret_key'));

        return hash_equals($expected, $signature);
    }
}
