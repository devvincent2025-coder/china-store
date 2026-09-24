<?php

namespace App\Notifications;

use App\Models\AdCampaign;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdCampaignApproved extends Notification implements ShouldQueue
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
            ->subject('Your store boost is live!')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$this->campaign->duration_days}-day store boost is now live and showing to buyers.")
            ->line('It runs until '.$this->campaign->ends_at->format('d M Y').'.')
            ->action('View Ad Metrics', url('/vendor/ads'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Your store boost is live!',
            body: 'Runs until '.$this->campaign->ends_at->format('d M Y'),
            actionUrl: url('/vendor/ads'),
            icon: 'check-circle',
        );
    }
}
