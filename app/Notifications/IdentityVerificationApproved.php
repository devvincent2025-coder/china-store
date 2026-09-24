<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IdentityVerificationApproved extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your identity verification is approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your identity verification (KYC) has been approved.')
            ->line('If you have any pending or future orders, your escrow payouts can now be released.')
            ->line('Your products are now visible to buyers, and you\'re marked as a verified seller.')
            ->action('Go to Dashboard', url('/vendor/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Identity verification approved',
            body: 'Your KYC is complete — you\'re a verified seller and your products are now visible to buyers.',
            actionUrl: url('/vendor/dashboard'),
            icon: 'check-circle',
        );
    }
}
