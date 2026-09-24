<?php

namespace App\Notifications;

use App\Models\VendorOrderDispute;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the buyer when an admin resolves their dispute WITHOUT a refund — either denied (escrow
 * released to the vendor as normal) or approved as a replacement (escrow still released, since the
 * vendor is fulfilling the order via a replacement item instead) — see
 * Filament\Resources\VendorOrderDisputeResource. The refund path already has its own notification
 * (OrderRefunded), triggered by the webhook once the refund actually clears. This decision is final
 * per the platform's buyer-protection policy — it does not reopen for further back-and-forth.
 */
class DisputeResolved extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorOrderDispute $dispute, private readonly string $resolutionNote)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorOrder = $this->dispute->vendorOrder;

        $summary = $this->dispute->status === 'approved_replace'
            ? 'The vendor will be sending you a replacement — payment has been released to them to cover it.'
            : 'After review, we found the order to be fulfilled as expected and have released payment to the vendor.';

        return (new MailMessage)
            ->subject('Update on your reported problem — Order '.$vendorOrder->order->order_number)
            ->greeting("Hi {$notifiable->name},")
            ->line('Our team has reviewed the problem you reported on order '.$vendorOrder->order->order_number.'.')
            ->line($this->resolutionNote ?: $summary)
            ->line('This decision is final.')
            ->action('View Order', route('orders.show', $vendorOrder->order->order_number));
    }

    public function toArray(object $notifiable): array
    {
        $vendorOrder = $this->dispute->vendorOrder;

        return $this->payload(
            title: 'Dispute resolved',
            body: 'Your report on order '.$vendorOrder->order->order_number.' has been reviewed.',
            actionUrl: route('orders.show', $vendorOrder->order->order_number),
        );
    }
}
