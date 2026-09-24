<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\Marketing\NewArrivalsNotification;
use App\Services\Notifications\NotificationCadence;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendNewArrivalsNotifications extends Command
{
    protected $signature = 'notifications:new-arrivals';

    protected $description = 'Notify buyers of new products from stores they\'ve bought from before — cadence set in Filament\Pages\Settings';

    private const LAST_SENT_KEY = 'notify_new_arrivals_last_sent_at';

    public function handle(): int
    {
        if (! NotificationCadence::isDue('notify_new_arrivals_cadence', self::LAST_SENT_KEY)) {
            $this->info('Not due yet.');

            return self::SUCCESS;
        }

        $lastSent = \App\Models\Setting::get(self::LAST_SENT_KEY);

        // First time this ever runs, there's no meaningful "since" — establish a baseline instead
        // of emailing every buyer about every product every vendor has ever added.
        if (! $lastSent) {
            NotificationCadence::markSent(self::LAST_SENT_KEY);
            $this->info('First run — baseline set, no emails sent.');

            return self::SUCCESS;
        }

        $since = Carbon::parse($lastSent);
        $sent = 0;

        $buyers = User::role('buyer')->whereHas('orders')->with('orders.vendorOrders')->get();

        foreach ($buyers as $buyer) {
            $vendorIds = $buyer->orders->flatMap->vendorOrders->pluck('vendor_id')->unique();

            foreach ($vendorIds as $vendorId) {
                $vendor = Vendor::with('store')->find($vendorId);

                if (! $vendor || ! $vendor->store) {
                    continue;
                }

                $newProducts = $vendor->products()
                    ->where('status', 'published')
                    ->where('created_at', '>', $since)
                    ->get();

                if ($newProducts->isNotEmpty()) {
                    $buyer->notify(new NewArrivalsNotification($vendor, $newProducts));
                    $sent++;
                }
            }
        }

        NotificationCadence::markSent(self::LAST_SENT_KEY);

        $this->info("Sent {$sent} new-arrivals notifications.");

        return self::SUCCESS;
    }
}
