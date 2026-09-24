<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Application received',
            body: 'We\'ve received your vendor application — continue onboarding to go live.',
            actionUrl: url('/vendor/onboarding'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $notifiable->vendor->business_name ?? $notifiable->name;

        $message = (new MailMessage)
            ->subject('Welcome to ChinaInNigeria — your vendor application is in')
            ->greeting("Hi {$businessName},")
            ->line("Thanks for applying to sell on ChinaInNigeria — we're excited to have you.")
            ->line('Two quick steps stand between you and your first sale:')
            ->line('1️⃣ Add your payout bank details — we verify those automatically within moments.')
            ->line('2️⃣ Our team reviews your submitted identity/business documents (usually within one business day).')
            ->line('Once both checks pass, your store goes live automatically — no further action needed from you.')
            ->action('Continue Onboarding', url('/vendor/onboarding'));

        return MailSender::hello($message);
    }
}
