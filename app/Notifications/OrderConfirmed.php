<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Order confirmed',
            body: "Order #{$this->order->order_number} — ₦".number_format($this->order->total / 100, 2).' paid',
            actionUrl: url("/orders/{$this->order->order_number}"),
            icon: 'order',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing(['vendorOrders.vendor', 'vendorOrders.items']);

        return (new MailMessage)
            ->subject("Order Confirmed — #{$this->order->order_number}")
            ->view(
                ['html' => 'emails.orders.confirmed', 'text' => 'emails.orders.confirmed-text'],
                ['order' => $this->order],
            );
    }
}
