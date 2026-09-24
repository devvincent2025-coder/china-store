<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
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
            title: 'Payment failed',
            body: "Order #{$this->order->order_number} — payment of ₦".number_format($this->order->total / 100, 2).' did not go through.',
            actionUrl: url("/orders/{$this->order->order_number}"),
            icon: 'warning',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment Failed — Order #{$this->order->order_number}")
            ->view(
                ['html' => 'emails.payments.failed', 'text' => 'emails.payments.failed-text'],
                ['order' => $this->order],
            );
    }
}
