<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use App\Support\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorBankVerificationResult extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(
        private readonly bool $success,
        private readonly ?string $resolvedName,
        private readonly bool $nameMatches,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: $this->success ? 'Bank account verified' : 'Bank verification failed',
            body: $this->success
                ? "Resolved account name: {$this->resolvedName}"
                : 'We couldn\'t verify your bank details — please double-check and resubmit.',
            actionUrl: url('/vendor/onboarding'),
            icon: $this->success ? 'check-circle' : 'warning',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $businessName = $notifiable->vendor->business_name ?? $notifiable->name;

        if (! $this->success) {
            $message = (new MailMessage)
                ->subject('We couldn\'t verify your bank details')
                ->greeting("Hi {$businessName},")
                ->line('We weren\'t able to verify the bank account you provided. Please double-check the account number and bank, then resubmit.')
                ->action('Update Bank Details', url('/vendor/onboarding'));

            return MailSender::hello($message);
        }

        $message = (new MailMessage)
            ->subject('Your bank account has been verified')
            ->greeting("Hi {$businessName},")
            ->line("We've verified your bank account. The resolved account name is: **{$this->resolvedName}**.");

        if (! $this->nameMatches) {
            $message->line('⚠️ Note: this name doesn\'t closely match your registered business name. Please make sure this is the correct payout account before proceeding.');
        }

        $message->action('Continue Onboarding', url('/vendor/onboarding'));

        return MailSender::hello($message);
    }
}
