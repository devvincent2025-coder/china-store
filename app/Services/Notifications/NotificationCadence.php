<?php

namespace App\Services\Notifications;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * Shared "is it time to send this yet?" check for the four admin-configurable marketing/engagement
 * emails (see Filament\Pages\Settings's "Automated email notifications" section). Each scheduled
 * command (App\Console\Commands\Send*Notifications) runs daily via the scheduler but only actually
 * sends when this says it's due — the cadence itself (off/daily/weekly/monthly) and the last-sent
 * timestamp both live in the same `settings` key/value store as everything else on that page.
 */
class NotificationCadence
{
    private const INTERVALS = [
        'daily' => 1,
        'weekly' => 7,
        'monthly' => 30,
    ];

    public static function isDue(string $cadenceKey, string $lastSentKey): bool
    {
        $cadence = Setting::get($cadenceKey, 'off');

        if (! isset(self::INTERVALS[$cadence])) {
            return false; // 'off', or an unrecognized value — never send.
        }

        $lastSent = Setting::get($lastSentKey);

        if (! $lastSent) {
            return true;
        }

        return Carbon::parse($lastSent)->addDays(self::INTERVALS[$cadence])->isPast();
    }

    public static function markSent(string $lastSentKey): void
    {
        Setting::set($lastSentKey, now()->toDateTimeString());
    }
}
