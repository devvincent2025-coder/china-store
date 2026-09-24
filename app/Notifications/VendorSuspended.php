<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the vendor's own user account when an admin suspends their store — see
 * App\Services\Vendor\VendorSuspensionService. A suspended vendor's dashboard becomes unreachable
 * (App\Http\Middleware\EnsureVendorIsActive) and their products stop being publicly listed.
 */
class VendorSuspended extends Notification implements ShouldQueue
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
            ->subject('Your store has been suspended')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your store on ChinaInNigeria has been suspended by our team.')
            ->line("Reason: {$this->reason}")
            ->line('If you believe this was a mistake or would like to appeal, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Your store has been suspended',
            body: $this->reason,
            icon: 'no-symbol',
        );
    }
}
