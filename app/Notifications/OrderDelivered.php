<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDelivered extends Notification implements ShouldQueue
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
            title: 'Order delivered',
            body: 'Your order from '.$this->vendorOrder->vendor->business_name.' has been delivered — leave a review?',
            actionUrl: url("/orders/{$this->vendorOrder->order->order_number}"),
            icon: 'check-circle',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorOrder->vendor->business_name;

        return (new MailMessage)
            ->subject("Delivered: your order from {$vendorName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order from {$vendorName} has been marked as delivered.")
            ->line('We\'d love to hear what you thought — a quick review helps other buyers and helps this vendor grow.')
            ->action('Leave a Review', url("/orders/{$this->vendorOrder->order->order_number}"));
    }
}
