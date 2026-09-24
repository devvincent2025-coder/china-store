<?php

namespace App\Notifications;

use App\Models\Coupon;
use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CouponAppliedConfirmation extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function __construct(private readonly Coupon $coupon, private readonly int $discountAmountKobo)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Coupon applied',
            body: "{$this->coupon->code} saved you ₦".number_format($this->discountAmountKobo / 100, 2),
            icon: 'money',
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Coupon {$this->coupon->code} applied")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your coupon **{$this->coupon->code}** saved you ₦".number_format($this->discountAmountKobo / 100, 2).' on your order.');
    }
}
