<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use App\Notifications\AdCampaignEndingSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily sweep that notifies a vendor 2 days before their currently-active campaign's ends_at, so
 * they have time to create a new one and avoid a gap in "Top Picks" visibility — see
 * App\Notifications\AdCampaignEndingSoon. ending_reminder_sent_at makes this idempotent: once sent,
 * a campaign is never re-notified even though this command runs every day for the whole 2-day
 * window it would otherwise match.
 */
class SendAdCampaignEndingReminders extends Command
{
    protected $signature = 'ads:send-ending-reminders';

    protected $description = "Notify vendors whose active ad campaign ends in 2 days";

    public function handle(): int
    {
        $campaigns = AdCampaign::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereNull('ending_reminder_sent_at')
            ->whereBetween('ends_at', [now()->addDays(2)->startOfDay(), now()->addDays(2)->endOfDay()])
            ->with('vendor.user')
            ->get();

        $sent = 0;

        foreach ($campaigns as $campaign) {
            try {
                $campaign->vendor->user->notify(new AdCampaignEndingSoon($campaign));
                $campaign->update(['ending_reminder_sent_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                Log::error('ads:send-ending-reminders failed for one campaign', [
                    'ad_campaign_id' => $campaign->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent ending-soon reminders for {$sent} of {$campaigns->count()} campaign(s).");

        return self::SUCCESS;
    }
}
