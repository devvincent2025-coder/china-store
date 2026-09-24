<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelled extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrder $vendorOrder)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorOrder->vendor->business_name;

        return (new MailMessage)
            ->subject("Order cancelled: {$vendorName}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order from {$vendorName} (order #{$this->vendorOrder->order->order_number}) has been cancelled.")
            ->line('If you were charged and this wasn\'t expected, contact support and we\'ll sort out a refund.')
            ->action('View Order', url("/orders/{$this->vendorOrder->order->order_number}"));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Order cancelled',
            body: 'Your order from '.$this->vendorOrder->vendor->business_name.' was cancelled.',
            actionUrl: url("/orders/{$this->vendorOrder->order->order_number}"),
            icon: 'warning',
        );
    }
}
