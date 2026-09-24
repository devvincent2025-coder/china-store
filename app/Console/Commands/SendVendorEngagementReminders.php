<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Notifications\Marketing\VendorEngagementReminder;
use App\Services\Notifications\NotificationCadence;
use Illuminate\Console\Command;

class SendVendorEngagementReminders extends Command
{
    protected $signature = 'notifications:vendor-engagement';

    protected $description = 'Remind vendors with few products or no active coupon to grow their store — cadence set in Filament\Pages\Settings';

    private const LOW_PRODUCT_THRESHOLD = 5;

    public function handle(): int
    {
        if (! NotificationCadence::isDue('notify_vendor_engagement_cadence', 'notify_vendor_engagement_last_sent_at')) {
            $this->info('Not due yet.');

            return self::SUCCESS;
        }

        $vendors = Vendor::where('status', 'active')
            ->withCount('products')
            ->with(['coupons' => fn ($query) => $query->where('is_active', true)])
            ->get();

        $sent = 0;

        foreach ($vendors as $vendor) {
            $needsProducts = $vendor->products_count < self::LOW_PRODUCT_THRESHOLD;
            $needsDiscount = $vendor->coupons->isEmpty();

            if (! $needsProducts && ! $needsDiscount) {
                continue;
            }

            $vendor->user->notify(new VendorEngagementReminder($needsProducts, $needsDiscount));
            $sent++;
        }

        NotificationCadence::markSent('notify_vendor_engagement_last_sent_at');

        $this->info("Sent engagement reminder to {$sent} vendors.");

        return self::SUCCESS;
    }
}
