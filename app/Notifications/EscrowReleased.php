<?php

namespace App\Notifications;

use App\Models\VendorOrder;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the vendor once their escrow payout for an order actually clears (transfer.success
 * webhook) — not when release is merely requested, since the bank transfer is asynchronous.
 */
class EscrowReleased extends Notification implements ShouldQueue
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
            title: 'Escrow payout released',
            body: '₦'.number_format($this->vendorOrder->payout_amount / 100, 2).' for order '.$this->vendorOrder->order->order_number,
            actionUrl: route('vendor.orders.show', $this->vendorOrder->id),
            icon: 'money',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Escrow payout released — Order '.$this->vendorOrder->order->order_number)
            ->greeting('Hi '.$this->vendorOrder->vendor->business_name.',')
            ->line('Your escrow payout of ₦'.number_format($this->vendorOrder->payout_amount / 100, 2).' for order '.$this->vendorOrder->order->order_number.' has been released to your bank account.')
            ->action('View Order', route('vendor.orders.show', $this->vendorOrder->id));
    }
}
