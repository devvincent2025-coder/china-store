<?php

namespace App\Notifications\Marketing;

use App\Models\Vendor;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a buyer when a vendor they've bought from before adds new products — see
 * App\Console\Commands\SendNewArrivalsNotifications.
 */
class NewArrivalsNotification extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    /**
     * @param  Collection<int, \App\Models\Product>  $products
     */
    public function __construct(private readonly Vendor $vendor, private readonly Collection $products)
    {
    }

    public function via(object $notifiable): array
    {
        // Marketing is the one category a buyer/vendor can actually opt out of — see
        // App\Http\Controllers\EmailPreferenceController and every email footer's unsubscribe link.
        return $notifiable->marketing_opt_out ? ['database'] : ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'New arrivals',
            body: "{$this->vendor->business_name} added {$this->products->count()} new product(s)",
            actionUrl: route('vendors.show', $this->vendor->store->slug),
            icon: 'package',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("New arrivals from {$this->vendor->business_name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->vendor->business_name}, a store you've shopped at before, just added new products:");

        foreach ($this->products->take(5) as $product) {
            $message->line('• '.($product->getTranslation('title', 'en') ?? $product->sku));
        }

        return $message->action('View New Arrivals', route('vendors.show', $this->vendor->store->slug));
    }
}
