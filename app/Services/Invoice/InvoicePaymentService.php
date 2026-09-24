<?php

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Notifications\InvoicePaymentReceipt;
use App\Notifications\InvoicePaymentReceived;
use App\Services\Korapay\KorapayService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Initializes a Korapay charge for one payment against an invoice — full or partial. Mirrors
 * App\Services\Checkout\CheckoutService's split logic: if the vendor has a Korapay subaccount, the
 * platform's commission is carried in the split the same way it is for ordinary cart checkouts, so
 * out-of-website sales are commissioned identically to on-platform ones. If the vendor has no
 * subaccount yet, the whole charge lands in the platform account with no split (same fallback
 * CheckoutService uses).
 */
class InvoicePaymentService
{
    public function __construct(private readonly KorapayService $korapay)
    {
    }

    public function initializePayment(Invoice $invoice, int $amountKobo, string $payerName, string $payerEmail, string $redirectUrl): array
    {
        $balance = $invoice->balanceDue();

        if ($amountKobo <= 0 || $amountKobo > $balance) {
            throw ValidationException::withMessages(['amount' => 'That amount is not valid for this invoice.']);
        }

        if (! $invoice->allow_partial_payment && $amountKobo !== $balance) {
            throw ValidationException::withMessages(['amount' => 'This invoice only accepts full payment.']);
        }

        if ($invoice->allow_partial_payment && $invoice->minimum_partial_amount && $amountKobo < $invoice->minimum_partial_amount && $amountKobo !== $balance) {
            throw ValidationException::withMessages(['amount' => 'That amount is below the minimum partial payment for this invoice.']);
        }

        $vendor = $invoice->vendor;

        $splits = [];

        if (filled($vendor->korapay_subaccount_id)) {
            $commission = $this->applyPercentage($amountKobo, (float) $vendor->commission_rate);

            $splits[] = [
                'subaccount_code' => $vendor->korapay_subaccount_id,
                'amount' => ($amountKobo - $commission) / 100,
            ];
        }

        $payment = $invoice->payments()->create([
            'reference' => 'INV-PAY-'.Str::upper(Str::random(12)),
            'amount' => $amountKobo,
            'status' => 'pending',
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
        ]);

        $charge = $this->korapay->initializeSplitCharge(
            reference: $payment->reference,
            amountNaira: $amountKobo / 100,
            customerEmail: $payerEmail,
            customerName: $payerName,
            splits: $splits,
            redirectUrl: $redirectUrl,
        );

        return [
            'payment' => $payment,
            'checkout_url' => $charge['checkout_url'] ?? null,
        ];
    }

    /**
     * Marks one payment confirmed and fires the vendor/payer notifications — shared by
     * KorapayWebhookController (the primary path) and InvoicePaymentController::callback() (a
     * synchronous fallback for when Korapay's webhook can never reach this app). Idempotent: safe
     * to call from both without double-notifying, since it no-ops once already 'success'.
     */
    public function confirmPayment(InvoicePayment $payment): void
    {
        if ($payment->status === 'success') {
            return;
        }

        $payment->update(['status' => 'success', 'paid_at' => now()]);

        $invoice = $payment->invoice;
        $invoice->increment('amount_paid', $payment->amount);
        $invoice->refresh();

        $invoice->update([
            'status' => $invoice->isFullyPaid() ? 'paid' : 'partially_paid',
            'paid_at' => $invoice->isFullyPaid() ? now() : $invoice->paid_at,
        ]);

        $invoice->vendor->user->notify(new InvoicePaymentReceived($payment));

        Notification::route('mail', $payment->payer_email)->notify(new InvoicePaymentReceipt($payment));
    }

    private function applyPercentage(int $amountKobo, float $percentage): int
    {
        $basisPoints = (int) round($percentage * 100);

        return intdiv($amountKobo * $basisPoints, 10000);
    }
}
