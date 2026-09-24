<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorDocumentsApproved extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Documents approved',
            body: 'Your identity/business verification documents have been approved.',
            icon: 'check-circle',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your documents have been approved')
            ->greeting('Hi '.($notifiable->vendor->business_name ?? $notifiable->name).',')
            ->line('Good news — our team has approved your identity/business verification documents.')
            ->line('Once your bank account is also verified, your store will go live automatically.');

        return MailSender::hello($message);
    }
}
