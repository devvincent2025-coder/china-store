<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorDocumentsRejected extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Documents not approved',
            body: "Reason: {$this->reason}",
            actionUrl: url('/vendor/onboarding'),
            icon: 'warning',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Action needed: your documents were not approved')
            ->greeting('Hi '.($notifiable->vendor->business_name ?? $notifiable->name).',')
            ->line('Unfortunately we were unable to approve the documents you submitted.')
            ->line("Reason: {$this->reason}")
            ->line('Please resubmit corrected documents to continue onboarding.')
            ->action('Resubmit Documents', url('/vendor/onboarding'));

        return MailSender::hello($message);
    }
}
