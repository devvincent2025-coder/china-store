<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use Illuminate\Console\Command;

/**
 * Daily sweep that flips any active/paused "boost your store" campaign whose paid-for window has
 * ended into 'expired' — AdCampaign::scopeCurrentlyLive() already excludes these from buyer-facing
 * pages regardless, so this just keeps the vendor/admin-facing status accurate rather than showing
 * a stale "active" badge forever.
 */
class ExpireAdCampaigns extends Command
{
    protected $signature = 'ads:expire';

    protected $description = 'Mark ad campaigns whose paid duration has ended as expired';

    public function handle(): int
    {
        $count = AdCampaign::query()
            ->whereIn('status', ['active', 'paused'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} ad campaign(s).");

        return self::SUCCESS;
    }
}
