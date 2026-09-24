<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer once a dispute-driven refund actually clears (refund.success webhook).
 */
class OrderRefunded extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrder $vendorOrder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Refund processed',
            body: 'Your refund for order '.$this->vendorOrder->order->order_number.' has been sent back to your payment method.',
            actionUrl: route('orders.show', $this->vendorOrder->order->order_number),
            icon: 'money',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Refund processed — Order '.$this->vendorOrder->order->order_number)
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your refund for the '.$this->vendorOrder->vendor->business_name.' portion of order '.$this->vendorOrder->order->order_number.' has been processed and sent back to your original payment method.')
            ->action('View Order', route('orders.show', $this->vendorOrder->order->order_number));
    }
}
