<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorNewOrderReceived extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrder $vendorOrder)
    {
    }

    public function via(object $notifiable): array
    {
        // The vendor's own channel preferences from Vendor Settings — in-app/database always stays
        // on regardless, since that's their own order queue, not an optional marketing channel.
        $vendor = $this->vendorOrder->vendor;

        $channels = ['database'];

        if ($vendor->notify_new_order_email) {
            $channels[] = 'mail';
        }

        if ($vendor->notify_new_order_push) {
            $channels[] = 'webpush';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'New order received! 🛍️',
            body: 'Order #'.$this->vendorOrder->order->order_number.' — ₦'.number_format($this->vendorOrder->total / 100, 2),
            actionUrl: url('/vendor/orders/'.$this->vendorOrder->id),
            icon: 'order',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You\'ve got a new order! 🛍️')
            ->greeting('Hi '.($this->vendorOrder->vendor->business_name ?? $notifiable->name).',')
            ->line("You've received a new order (#{$this->vendorOrder->order->order_number}) worth ₦".number_format($this->vendorOrder->total / 100, 2).'.')
            ->line('Please prepare it for shipment and update the tracking details from your vendor dashboard once it\'s on its way.')
            ->action('View Order', url('/vendor/orders/'.$this->vendorOrder->id));
    }
}
