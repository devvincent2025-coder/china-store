<?php

namespace App\Notifications;

use App\Models\AdCampaign;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdCampaignRejected extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly AdCampaign $campaign, private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your store boost was not approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('We were unable to approve your recent store boost campaign.')
            ->line('Reason: '.$this->reason)
            ->line('Please contact support if you have questions about your payment.')
            ->action('Contact Support', url('/support/create'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Store boost not approved',
            body: $this->reason,
            actionUrl: url('/vendor/ads'),
            icon: 'warning',
        );
    }
}
