<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer named on the invoice — who may have no platform account at all, since this
 * is for an out-of-website sale. Delivered via Notification::route('mail', ...) rather than
 * ->notify() on a User, so it works for any email address.
 */
class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Invoice $invoice)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice;

        $message = (new MailMessage)
            ->subject("Invoice {$invoice->invoice_number} from {$invoice->vendor->business_name}")
            ->greeting("Hi {$invoice->customer_name},")
            ->line("{$invoice->vendor->business_name} has sent you an invoice.");

        foreach ($invoice->items as $item) {
            $message->line("{$item->description} — {$item->quantity} x ₦".number_format($item->unit_price / 100, 2).' = ₦'.number_format($item->line_total / 100, 2));
        }

        $message->line('Total: ₦'.number_format($invoice->total / 100, 2));

        if ($invoice->due_date) {
            $message->line('Due date: '.$invoice->due_date->format('d M Y'));
        }

        if ($invoice->allow_partial_payment) {
            $message->line('This invoice accepts part payments — you can pay in installments toward the total.');
        }

        return $message
            ->action('Pay Invoice', route('invoices.pay.show', $invoice->public_token))
            ->line('Thank you for your business.');
    }
}
