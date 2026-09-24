<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
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
            title: 'Order shipped',
            body: $this->vendorOrder->vendor->business_name.' has shipped order #'.$this->vendorOrder->order->order_number,
            actionUrl: url("/orders/{$this->vendorOrder->order->order_number}"),
            icon: 'package',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorOrder->vendor->business_name;

        $message = (new MailMessage)
            ->subject("Your order from {$vendorName} has shipped")
            ->greeting("Hi {$notifiable->name},")
            ->line("Good news — {$vendorName} has shipped part of your order #{$this->vendorOrder->order->order_number}.");

        if ($this->vendorOrder->tracking_number) {
            $message->line("Tracking number: **{$this->vendorOrder->tracking_number}**".
                ($this->vendorOrder->tracking_carrier ? " via {$this->vendorOrder->tracking_carrier}" : ''));
        }

        return $message->action('Track Your Order', url("/orders/{$this->vendorOrder->order->order_number}"));
    }
}
