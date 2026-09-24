<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\Marketing\NewStoreAnnouncement;
use App\Services\Notifications\NotificationCadence;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendNewStoreAnnouncements extends Command
{
    protected $signature = 'notifications:new-stores';

    protected $description = 'Announce newly onboarded vendor stores to buyers — cadence set in Filament\Pages\Settings';

    private const LAST_SENT_KEY = 'notify_new_stores_last_sent_at';

    public function handle(): int
    {
        if (! NotificationCadence::isDue('notify_new_stores_cadence', self::LAST_SENT_KEY)) {
            $this->info('Not due yet.');

            return self::SUCCESS;
        }

        $lastSent = Setting::get(self::LAST_SENT_KEY);

        if (! $lastSent) {
            NotificationCadence::markSent(self::LAST_SENT_KEY);
            $this->info('First run — baseline set, no emails sent.');

            return self::SUCCESS;
        }

        // korapay_subaccount_created_at is the one-time, immutable moment a vendor's payout
        // destination was provisioned — the final automated onboarding step — so it's a much more
        // reliable "went live" marker than status/updated_at, which can change for unrelated reasons.
        $newVendors = Vendor::where('status', 'active')
            ->whereNotNull('korapay_subaccount_created_at')
            ->where('korapay_subaccount_created_at', '>', Carbon::parse($lastSent))
            ->with('store')
            ->get()
            ->filter(fn (Vendor $vendor) => $vendor->store !== null);

        if ($newVendors->isEmpty()) {
            NotificationCadence::markSent(self::LAST_SENT_KEY);
            $this->info('No new stores since last send.');

            return self::SUCCESS;
        }

        $buyers = User::role('buyer')->get();

        Notification::send($buyers, new NewStoreAnnouncement($newVendors));

        NotificationCadence::markSent(self::LAST_SENT_KEY);

        $this->info("Announced {$newVendors->count()} new store(s) to {$buyers->count()} buyers.");

        return self::SUCCESS;
    }
}
