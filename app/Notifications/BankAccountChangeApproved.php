<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankAccountChangeApproved extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your new payout bank account is confirmed')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your payout bank account change has been approved and is now active.')
            ->line("We're re-verifying the account with your bank now — you'll get a separate confirmation shortly.")
            ->action('View Profile', url('/vendor/profile'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Bank account change approved',
            body: 'Your new payout bank account is now active.',
            actionUrl: url('/vendor/profile'),
            icon: 'check-circle',
        );
    }
}
