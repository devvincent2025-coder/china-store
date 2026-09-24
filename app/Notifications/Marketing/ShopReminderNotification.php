<?php

namespace App\Notifications\Marketing;

use App\Notifications\Concerns\BuildsDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Periodic buyer re-engagement email — cadence set from Filament\Pages\Settings, sent by
 * App\Console\Commands\SendShopReminderNotifications.
 */
class ShopReminderNotification extends Notification implements ShouldQueue
{
    use Queueable, BuildsDatabasePayload;

    public function via(object $notifiable): array
    {
        // Marketing is the one category a buyer/vendor can actually opt out of — see
        // App\Http\Controllers\EmailPreferenceController and every email footer's unsubscribe link.
        return $notifiable->marketing_opt_out ? ['database'] : ['mail', 'database', 'webpush'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload(
            title: 'Unbeatable prices are waiting',
            body: 'Fresh stock from Chinese-run stores across Nigeria — come take a look.',
            actionUrl: route('products.index'),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Unbeatable prices are waiting for you at ChinaInNigeria')
            ->greeting("Hi {$notifiable->name},")
            ->line("It's been a while! Chinese-run stores across Nigeria have fresh stock and unbeatable prices ready for you.")
            ->line('Browse thousands of products across electronics, fashion, beauty, home & kitchen, and more.')
            ->action('Start Shopping', route('products.index'));
    }
}
