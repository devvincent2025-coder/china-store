<?php

namespace App\Notifications;

use App\Models\VendorReport;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin when a buyer reports a VENDOR/store directly — see
 * Http\Controllers\VendorReportController and Filament\Resources\VendorReportResource. Distinct
 * from NewProductReport, which is about one specific listing rather than the whole account.
 */
class NewVendorReport extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly VendorReport $report)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendor = $this->report->vendor;

        return (new MailMessage)
            ->subject('New vendor report: '.$vendor->business_name)
            ->greeting("Hi {$notifiable->name},")
            ->line('A buyer reported a vendor: '.$vendor->business_name)
            ->line('Reason: '.ucfirst(str_replace('_', ' ', $this->report->reason)))
            ->line('"'.$this->report->description.'"')
            ->action('Review Report', url('/admin/vendor-reports/'.$this->report->id));
    }

    public function toArray(object $notifiable): array
    {
        $vendor = $this->report->vendor;

        return $this->payload(
            title: 'New vendor report',
            body: $vendor->business_name.' — '.ucfirst(str_replace('_', ' ', $this->report->reason)),
            actionUrl: url('/admin/vendor-reports/'.$this->report->id),
            icon: 'warning',
        );
    }
}
