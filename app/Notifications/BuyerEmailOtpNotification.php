<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Sent to a not-yet-existing buyer's email address via an on-demand notification (see
 * Notification::route('mail', $email) in BuyerSignupService::startRegistration()) — there's no
 * User row to attach this to yet, since the account isn't created until the code is verified.
 */
class BuyerEmailOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ChinaInNigeria verification code')
            ->greeting('Hi there,')
            ->line('Use this code to verify your email address and finish creating your account:')
            ->line(new HtmlString(
                '<div style="margin:24px 0;text-align:center;font-size:32px;font-weight:700;letter-spacing:0.5em;color:#111827;">'.e($this->code).'</div>'
            ))
            ->line('This code expires in 10 minutes.')
            ->line("If you didn't try to create an account, you can safely ignore this email.");
    }
}
