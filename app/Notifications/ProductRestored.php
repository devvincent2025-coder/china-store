<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin reverses a takedown — see ProductTakenDown.
 */
class ProductRestored extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Product $product)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your product has been restored')
            ->greeting("Hi {$notifiable->vendor->business_name},")
            ->line('Good news — the following product has been restored and is available again:')
            ->line('**'.$this->product->getTranslation('title', 'en').'**')
            ->action('View Product', route('vendor.products.edit', $this->product->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Product restored',
            body: $this->product->getTranslation('title', 'en').' is live again.',
            actionUrl: route('vendor.products.edit', $this->product->id),
            icon: 'check-circle',
        );
    }
}
