<?php

namespace App\Notifications;

use App\Models\AdCampaign;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent 2 days before an active campaign's ends_at — see
 * App\Console\Commands\SendAdCampaignEndingReminders. Purely a "create another one" nudge, not a
 * warning of anything going wrong, so it always goes out regardless of marketing_opt_out (this is
 * about the vendor's own paid, currently-running campaign, not a marketing email).
 */
class AdCampaignEndingSoon extends Notification implements ShouldQueue
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
            ->subject('Your store boost ends in 2 days')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your {$this->campaign->duration_days}-day store boost stops showing to buyers on {$this->campaign->ends_at->format('d M Y')}.")
            ->line('Create a new campaign now to keep your store appearing in "Top Picks" without a gap.')
            ->action('Boost My Store Again', url('/vendor/ads/create'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Your store boost ends in 2 days',
            body: 'Ends '.$this->campaign->ends_at->format('d M Y').' — create a new one to avoid a gap.',
            actionUrl: url('/vendor/ads/create'),
            icon: 'clock',
        );
    }
}
