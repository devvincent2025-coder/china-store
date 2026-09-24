<?php

namespace App\Notifications;

use App\Models\ProductReport;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin when a buyer reports a product — see
 * Http\Controllers\ProductReportController and Filament\Resources\ProductReportResource.
 */
class NewProductReport extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly ProductReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->report->product;

        return (new MailMessage)
            ->subject('New product report: '.$product->getTranslation('title', 'en'))
            ->greeting("Hi {$notifiable->name},")
            ->line('A buyer reported a product: '.$product->getTranslation('title', 'en'))
            ->line('Reason: '.ucfirst($this->report->reason))
            ->line('"'.$this->report->description.'"')
            ->action('Review Report', url('/admin/product-reports/'.$this->report->id));
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->report->product;

        return $this->payload(
            title: 'New product report',
            body: $product->getTranslation('title', 'en').' — '.ucfirst($this->report->reason),
            actionUrl: url('/admin/product-reports/'.$this->report->id),
            icon: 'warning',
        );
    }
}
