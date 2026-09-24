<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Fully public, unauthenticated — the customer named on a vendor-issued invoice may have no
 * platform account, so this is reached only via the invoice's own unguessable public_token
 * (see routes/web.php). Never register these under the 'auth' middleware group.
 */
class InvoicePaymentController extends Controller
{
    public function __construct(
        private readonly InvoicePaymentService $paymentService,
        private readonly KorapayService $korapay,
    ) {
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load('items', 'vendor');

        return Inertia::render('Invoices/Pay', [
            'invoice' => [
                'invoice_number' => $invoice->invoice_number,
                'public_token' => $invoice->public_token,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'customer_name' => $invoice->customer_name,
                'customer_email' => $invoice->customer_email,
                'subtotal' => $invoice->subtotal,
                'discount' => $invoice->discount,
                'vat_amount' => $invoice->vat_amount,
                'shipping_amount' => $invoice->shipping_amount,
                'total' => $invoice->total,
                'amount_paid' => $invoice->amount_paid,
                'balance_due' => $invoice->balanceDue(),
                'allow_partial_payment' => $invoice->allow_partial_payment,
                'minimum_partial_amount' => $invoice->minimum_partial_amount,
                'due_date' => $invoice->due_date?->toDateString(),
                'notes' => $invoice->notes,
                'items' => $invoice->items,
                'vendor' => ['business_name' => $invoice->vendor->business_name],
            ],
        ]);
    }

    public function store(Request $request, Invoice $invoice): SymfonyResponse
    {
        if (in_array($invoice->status, ['void', 'paid'], true)) {
            return back()->with('error', 'This invoice cannot be paid.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],
        ]);

        $result = $this->paymentService->initializePayment(
            invoice: $invoice,
            amountKobo: (int) round($validated['amount'] * 100),
            payerName: $validated['payer_name'],
            payerEmail: $validated['payer_email'],
            redirectUrl: route('invoices.pay.callback', $invoice->public_token),
        );

        // Korapay's checkout page is a different origin, so a plain redirect()->away() would be
        // followed by Inertia's XHR client itself (and blocked by CORS) rather than navigating the
        // browser tab. Inertia::location() replies with a 409 + X-Inertia-Location header, which the
        // frontend recognizes as a hard `window.location` navigation instead of an Inertia visit.
        return Inertia::location($result['checkout_url'] ?? route('invoices.pay.show', $invoice->public_token));
    }

    /**
     * Korapay redirects the payer's browser here after paying. The webhook (see
     * Http\Controllers\Webhooks\KorapayWebhookController) is the primary confirmation path, but it
     * can only ever reach a publicly reachable webhook URL — in local development (and anywhere
     * Korapay's servers can't reach) it never arrives. So this also actively verifies the most
     * recent pending payment as a synchronous fallback — see InvoicePaymentService::confirmPayment(),
     * which is idempotent, so running both paths is safe.
     */
    public function callback(Invoice $invoice): RedirectResponse
    {
        $payment = $invoice->payments()->where('status', 'pending')->latest()->first();

        if ($payment) {
            try {
                $result = $this->korapay->verifyCharge($payment->reference);

                if (($result['status'] ?? null) === 'success') {
                    $this->paymentService->confirmPayment($payment);
                }
            } catch (KorapayRequestException $e) {
                Log::warning('Invoice payment verification failed on callback', [
                    'invoice_payment_id' => $payment->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('invoices.pay.show', $invoice->public_token);
    }
}
