<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Welcome to ChinaInNigeria',
            body: 'Browse thousands of products from verified vendors, all shipped and sold in Naira.',
            actionUrl: url('/'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome to ChinaInNigeria, '.$notifiable->name)
            ->greeting("Hi {$notifiable->name},")
            ->line("You're in! Thanks for joining ChinaInNigeria — your marketplace for Chinese stores operating right here in Nigeria.")
            ->line('Here\'s what you can do next:')
            ->line('🛍️ Browse thousands of products from verified vendors, all shipped and sold in Naira.')
            ->line('🔒 Shop with confidence — every order is protected by our buyer escrow, so payment is only released to a vendor once you\'ve confirmed delivery.')
            ->line('📦 Track every order in real time, right from your account.')
            ->action('Start Shopping', url('/'))
            ->line('If you ever have a question, our support team is one click away.');

        return MailSender::hello($message);
    }
}
