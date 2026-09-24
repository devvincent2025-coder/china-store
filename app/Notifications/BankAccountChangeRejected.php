<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankAccountChangeRejected extends Notification implements ShouldQueue
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
            ->subject('Your bank account change request was not approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('We were unable to approve your recent payout bank account change request.')
            ->line('Reason: '.$this->reason)
            ->line('Your existing bank account on file is unaffected. Please contact support if you have questions.')
            ->action('Contact Support', url('/support/create'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Bank account change not approved',
            body: $this->reason,
            actionUrl: url('/vendor/profile'),
            icon: 'warning',
        );
    }
}
