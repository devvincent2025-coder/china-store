<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to admins when a product is AUTOMATICALLY taken down for accumulating too many open reports
 * (see ProductReportController::store() and ProductTakedownService::autoFlagForReports()) — this
 * is the "needs a human to actually investigate and decide whether the vendor should be suspended"
 * alert, since the system only ever pulls the product down, it never touches the vendor's account.
 */
class ProductAutoFlagged extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Product $product, private readonly int $reportCount)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Product auto-flagged after '.$this->reportCount.' reports')
            ->greeting("Hi {$notifiable->name},")
            ->line('A product was automatically taken down and needs your review:')
            ->line('**'.$this->product->getTranslation('title', 'en').'** — sold by '.$this->product->vendor->business_name)
            ->line("It has reached {$this->reportCount} open reports.")
            ->line('Please investigate and decide whether the vendor should be suspended.')
            ->action('Review Reports', route('filament.admin.resources.product-reports.index'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Product auto-flagged after '.$this->reportCount.' reports',
            body: $this->product->getTranslation('title', 'en').' — '.$this->product->vendor->business_name,
            actionUrl: route('filament.admin.resources.product-reports.index'),
            icon: 'flag',
        );
    }
}
