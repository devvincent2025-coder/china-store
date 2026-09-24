<?php

namespace App\Notifications;

use App\Models\InvoicePayment;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the vendor (not the customer — see InvoicePaymentReceipt for that) whenever a charge
 * against one of their invoices succeeds, full or partial.
 */
class InvoicePaymentReceived extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly InvoicePayment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        $invoice = $this->payment->invoice;

        return $this->payload(
            title: 'Invoice payment received',
            body: "{$invoice->customer_name} paid ₦".number_format($this->payment->amount / 100, 2)." toward invoice {$invoice->invoice_number}",
            actionUrl: route('vendor.invoices.show', $invoice->id),
            icon: 'money',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->payment->invoice;
        $balance = $invoice->balanceDue();

        $message = (new MailMessage)
            ->subject("Payment received — Invoice {$invoice->invoice_number}")
            ->greeting('Hi '.$invoice->vendor->business_name.',')
            ->line("{$invoice->customer_name} paid ₦".number_format($this->payment->amount / 100, 2)." toward invoice {$invoice->invoice_number}.");

        if ($balance > 0) {
            $message->line('Remaining balance: ₦'.number_format($balance / 100, 2));
        } else {
            $message->line('This invoice is now fully paid.');
        }

        return $message->action('View Invoice', route('vendor.invoices.show', $invoice->id));
    }
}
