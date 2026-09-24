<?php

namespace App\Notifications;

use App\Models\AdCampaign;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every admin/super_admin once a vendor's ad campaign payment is confirmed — see
 * App\Services\Ads\AdCampaignService::markPaid() and Filament\Resources\AdCampaignResource.
 */
class NewAdCampaignSubmitted extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly AdCampaign $campaign)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ad campaign awaiting approval: '.$this->campaign->vendor->business_name)
            ->greeting("Hi {$notifiable->name},")
            ->line("{$this->campaign->vendor->business_name} paid for a {$this->campaign->duration_days}-day store boost and it's awaiting your approval.")
            ->action('Review Campaign', url('/admin/ad-campaigns/'.$this->campaign->id));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Ad campaign awaiting approval',
            body: $this->campaign->vendor->business_name.' paid for a store boost',
            actionUrl: url('/admin/ad-campaigns/'.$this->campaign->id),
            icon: 'money',
        );
    }
}
