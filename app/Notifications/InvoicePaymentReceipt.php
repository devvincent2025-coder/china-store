<?php

namespace App\Notifications;

use App\Models\InvoicePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Receipt sent to the payer's email (may differ from the invoice's customer_email if they edited
 * it at payment time). See InvoicePaymentReceived for the vendor-facing notification.
 */
class InvoicePaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly InvoicePayment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;
        $balance = $invoice->balanceDue();

        $message = (new MailMessage)
            ->subject("Payment receipt — Invoice {$invoice->invoice_number}")
            ->greeting('Hi '.$this->payment->payer_name.',')
            ->line('We received your payment of ₦'.number_format($this->payment->amount / 100, 2)." to {$invoice->vendor->business_name} for invoice {$invoice->invoice_number}.");

        if ($balance > 0) {
            $message->line('Remaining balance: ₦'.number_format($balance / 100, 2));
        } else {
            $message->line('This invoice is now fully paid. Thank you!');
        }

        return $message->action('View Invoice', route('invoices.pay.show', $invoice->public_token));
    }
}
