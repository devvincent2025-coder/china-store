<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Marketing\ShopReminderNotification;
use App\Services\Notifications\NotificationCadence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendShopReminderNotifications extends Command
{
    protected $signature = 'notifications:shop-reminder';

    protected $description = 'Remind buyers to come back and shop — cadence set in Filament\Pages\Settings';

    public function handle(): int
    {
        if (! NotificationCadence::isDue('notify_shop_reminder_cadence', 'notify_shop_reminder_last_sent_at')) {
            $this->info('Not due yet.');

            return self::SUCCESS;
        }

        $buyers = User::role('buyer')->get();

        Notification::send($buyers, new ShopReminderNotification);

        NotificationCadence::markSent('notify_shop_reminder_last_sent_at');

        $this->info("Sent shop reminder to {$buyers->count()} buyers.");

        return self::SUCCESS;
    }
}
