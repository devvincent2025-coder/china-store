<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IdentityVerificationRejected extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action needed: your identity verification was not approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('We were unable to approve the identity document you submitted.')
            ->line('Reason: '.$this->reason)
            ->line('Please upload a clearer document to try again.')
            ->action('Try Again', url('/vendor/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Identity verification not approved',
            body: $this->reason,
            actionUrl: url('/vendor/dashboard'),
            icon: 'warning',
        );
    }
}
