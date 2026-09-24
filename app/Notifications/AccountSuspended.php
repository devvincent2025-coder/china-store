<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an admin suspends a buyer or vendor account — see
 * Filament\Resources\UserResource::suspendAction().
 */
class AccountSuspended extends Notification implements ShouldQueue
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
            ->subject('Your account has been suspended')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your ChinaInNigeria account has been suspended.')
            ->line("Reason: {$this->reason}")
            ->line('Contact support if you believe this is a mistake.');
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Account suspended',
            body: $this->reason,
            icon: 'warning',
        );
    }
}
