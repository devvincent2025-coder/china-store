<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the vendor when an admin takes one of their products down — usually following a buyer's
 * report (see ProductReport / ProductReportResource) but also usable for a direct takedown.
 */
class ProductTakenDown extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Product $product, private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your product has been taken down: '.$this->product->getTranslation('title', 'en'))
            ->greeting("Hi {$notifiable->vendor->business_name},")
            ->line('Our team has taken down the following product from your store:')
            ->line('**'.$this->product->getTranslation('title', 'en').'**')
            ->line("Reason: {$this->reason}")
            ->line('If you believe this was a mistake, please contact support.')
            ->action('View Product', route('vendor.products.edit', $this->product->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Product taken down',
            body: $this->product->getTranslation('title', 'en').' — '.$this->reason,
            actionUrl: route('vendor.products.edit', $this->product->id),
            icon: 'warning',
        );
    }
}
