<?php

namespace App\Notifications;

use App\Models\VendorOrderDispute;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when a vendor submits their side of a dispute — see
 * Vendor\OrderController::respondToDispute(). Lets an admin resolve the case as soon as both sides
 * are in, rather than only discovering a response by revisiting the record after the window passes.
 */
class VendorRespondedToDispute extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrderDispute $dispute)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorOrder = $this->dispute->vendorOrder;

        return (new MailMessage)
            ->subject('Vendor responded to a dispute — Order '.$vendorOrder->order->order_number)
            ->greeting("Hi {$notifiable->name},")
            ->line('The vendor on order '.$vendorOrder->order->order_number.' has responded to the buyer\'s dispute:')
            ->line('"'.$this->dispute->vendor_response.'"')
            ->action('Review Dispute', route('filament.admin.resources.vendor-order-disputes.view', $this->dispute));
    }

    public function toArray(object $notifiable): array
    {
        $vendorOrder = $this->dispute->vendorOrder;

        return $this->payload(
            title: 'Vendor responded to a dispute',
            body: 'Order '.$vendorOrder->order->order_number,
            actionUrl: route('filament.admin.resources.vendor-order-disputes.view', $this->dispute),
            icon: 'warning',
        );
    }
}
