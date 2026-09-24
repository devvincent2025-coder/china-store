<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail-only — the OTP itself is the whole point of this message, so it never touches the database
 * or push channels (nothing worth showing in a bell icon, and definitely nothing to persist).
 */
class BankAccountChangeOtp extends Notification implements ShouldQueue
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
            ->subject('Confirm your payout bank account change')
            ->greeting("Hi {$notifiable->business_name},")
            ->line('You requested to change the bank account your payouts are sent to. Enter this code to confirm the request:')
            ->line(new \Illuminate\Support\HtmlString('<p style="font-size:28px;font-weight:700;letter-spacing:6px;text-align:center;color:#FF5000;margin:16px 0;">'.$this->code.'</p>'))
            ->line('This code expires in 10 minutes. After you confirm it, our support team will still need to call you and an admin will approve the change before it takes effect — this extra step protects your payouts.')
            ->line("If you didn't request this, you can safely ignore this email — your bank details won't change.");
    }
}
