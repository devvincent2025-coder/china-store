<?php

namespace App\Notifications;

use App\Models\VendorOrderDispute;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the vendor as soon as a buyer opens a dispute — this is what pauses escrow release on
 * that order (see VendorOrder::isEligibleForEscrowRelease()) until an admin resolves it, so the
 * vendor should know right away rather than only discovering it when their payout doesn't arrive.
 */
class VendorOrderDisputeOpened extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrderDispute $dispute)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        $vendorOrder = $this->dispute->vendorOrder;

        return $this->payload(
            title: 'Buyer reported a problem',
            body: 'Order '.$vendorOrder->order->order_number.': '.ucfirst(str_replace('_', ' ', $this->dispute->reason)),
            actionUrl: route('vendor.orders.show', $vendorOrder->id),
            icon: 'warning',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorOrder = $this->dispute->vendorOrder;

        return (new MailMessage)
            ->subject('A buyer reported a problem — Order '.$vendorOrder->order->order_number)
            ->greeting('Hi '.$vendorOrder->vendor->business_name.',')
            ->line('The buyer on order '.$vendorOrder->order->order_number.' reported a problem: '.ucfirst(str_replace('_', ' ', $this->dispute->reason)).'.')
            ->line('"'.$this->dispute->description.'"')
            ->line('Escrow release for this order is paused while our team reviews it.')
            ->line('You have until '.$this->dispute->vendor_response_deadline->format('d M Y, g:i a').' ('.VendorOrderDispute::RESPONSE_WINDOW_HOURS.' hours) to respond with your side and any evidence (photos, packaging, delivery confirmation) directly on the order page. If we don\'t hear from you, or the two sides can\'t agree, our team will make a final decision based on what\'s available.')
            ->action('Respond to Dispute', route('vendor.orders.show', $vendorOrder->id));
    }
}
