<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorReactivated extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your store has been reactivated')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your store on ChinaInNigeria has been reactivated — you can resume selling right away.')
            ->action('Go to Dashboard', url('/vendor/dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Your store has been reactivated',
            body: 'You can resume selling right away.',
            actionUrl: url('/vendor/dashboard'),
            icon: 'check-circle',
        );
    }
}
