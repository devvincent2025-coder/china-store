<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorFullyApproved extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'You\'re live! 🎉',
            body: 'Your store is now fully approved and live on ChinaInNigeria.',
            actionUrl: url('/vendor/dashboard'),
            icon: 'check-circle',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('You\'re live on ChinaInNigeria! 🎉')
            ->greeting('Hi '.($notifiable->vendor->business_name ?? $notifiable->name).',')
            ->line('Congratulations — your store is now fully approved and live on ChinaInNigeria.')
            ->line('You can start listing products right away. Payments from your sales will be routed to your verified bank account automatically after each order.')
            ->action('Go to Your Dashboard', url('/vendor/dashboard'));

        return MailSender::hello($message);
    }
}
