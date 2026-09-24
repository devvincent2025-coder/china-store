<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountUnsuspended extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been reinstated')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your ChinaInNigeria account is no longer suspended — you can log in as normal.')
            ->action('Log In', route('login'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Account reinstated',
            body: 'Your account is no longer suspended.',
            actionUrl: route('login'),
            icon: 'check-circle',
        );
    }
}
