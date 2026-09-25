<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Escrow auto-release — see App\Console\Commands\ReleaseEligibleEscrows and EscrowService.
Schedule::command('escrow:release-eligible')->daily();

// Store-boost ad campaigns — see App\Console\Commands\ExpireAdCampaigns. Hourly (not daily) so a
// campaign's status stops showing "active" within the hour its paid window actually ends, not up
// to a day late.
Schedule::command('ads:expire')->hourly();

// "Your boost ends in 2 days" nudge — see App\Console\Commands\SendAdCampaignEndingReminders.
Schedule::command('ads:send-ending-reminders')->daily();

// Marketing/engagement emails — each command runs daily but self-throttles against the cadence an
// admin sets in Filament\Pages\Settings (off/daily/weekly/monthly), via NotificationCadence.
Schedule::command('notifications:shop-reminder')->daily();
Schedule::command('notifications:new-arrivals')->daily();
Schedule::command('notifications:vendor-engagement')->daily();
Schedule::command('notifications:new-stores')->daily();

// Sitemap export + live cache refresh — see App\Console\Commands\ExportSitemap.
Schedule::command('sitemap:export --gzip')->dailyAt('02:30');

// Shared hosting has no Supervisor/systemd to keep `queue:work` running as a persistent daemon —
// every queued notification (order confirmations, vendor approvals, escrow/dispute emails, ...)
// would otherwise just sit in the `jobs` table until someone happens to SSH in and run it by hand.
// This piggybacks on the SAME cron-triggered `schedule:run` that already drives every command
// above: each minute it drains whatever's currently queued and exits — `--stop-when-empty` means
// it never lingers as a long-running process (which a shared host's process limits would kill
// anyway), and `withoutOverlapping()` skips a run if the previous minute's is still mid-flight
// (e.g. a slow SMTP send) rather than starting a second worker on top of it.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
