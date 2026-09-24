<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AdCampaignApproved;
use App\Notifications\AdCampaignRejected;
use App\Notifications\NewAdCampaignSubmitted;
use App\Services\Korapay\KorapayService;
use App\Services\Storage\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The vendor "boost your store" advertising feature — a paid, admin-approved placement shown as a
 * "Top Picks / Recommended for you" card on the homepage and a few other buyer-facing surfaces
 * (Category, Products search — see Http\Controllers\HomeController, CategoryController,
 * ProductController for where scopeCurrentlyLive() campaigns are actually fetched and rendered).
 *
 * Lifecycle: pending_payment (just created) -> pending_approval (Korapay charge confirmed, webhook
 * landed) -> active (admin approved — this is the "did we actually receive the money and does this
 * banner look legitimate" checkpoint) -> paused/active (vendor's own toggle) -> expired (once
 * ends_at passes — see App\Console\Commands\ExpireAdCampaigns). A rejected campaign is NOT
 * automatically refunded; see rejectCampaign()'s doc comment.
 */
class AdCampaignService
{
    public function __construct(
        private readonly KorapayService $korapay,
        private readonly SupabaseStorageService $storage,
    ) {
    }

    public function costPerDayKobo(): int
    {
        return (int) Setting::get('ad_cost_per_day_kobo', 500000); // ₦5,000/day default
    }

    public function minDays(): int
    {
        return (int) Setting::get('ad_min_days', 3);
    }

    public function maxDays(): int
    {
        return (int) Setting::get('ad_max_days', 30);
    }

    public function estimateCostKobo(int $days): int
    {
        return $days * $this->costPerDayKobo();
    }

    /**
     * Creates the campaign row in pending_payment — nothing is shown to buyers, and no money has
     * moved, until initiatePayment() below is paid and an admin approves it. $startsAt is the day
     * the VENDOR chooses to actually run it (shown the mandatory terms modal first — see
     * Vendor/Ads/Create.jsx — since a paid campaign can't be cancelled, only ever run later than
     * planned by picking a later date here). approve() below respects this date rather than always
     * starting the clock at approval time.
     */
    public function create(Vendor $vendor, int $days, ?UploadedFile $bannerFile, ?Carbon $startsAt = null): AdCampaign
    {
        if ($days < $this->minDays() || $days > $this->maxDays()) {
            throw ValidationException::withMessages([
                'duration_days' => "Duration must be between {$this->minDays()} and {$this->maxDays()} days.",
            ]);
        }

        if ($startsAt && $startsAt->startOfDay()->isBefore(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'starts_at' => 'The start date must be today or later.',
            ]);
        }

        $bannerPath = null;
        $bannerUrl = null;

        if ($bannerFile) {
            $uploaded = $this->storage->uploadPublic($bannerFile->getRealPath(), 'ad-banners');
            $bannerPath = $uploaded['path'];
            $bannerUrl = $uploaded['url'];
        } else {
            // Defaults to the vendor's own store feature image — a vendor who just wants to boost
            // visibility without designing a banner can skip this step entirely.
            $store = $vendor->store;
            $bannerUrl = $store?->banner_url ?? $store?->logo_url;
        }

        return $vendor->adCampaigns()->create([
            'banner_path' => $bannerPath,
            'banner_url' => $bannerUrl,
            'duration_days' => $days,
            'starts_at' => $startsAt?->startOfDay(),
            'status' => 'pending_payment',
            'amount_kobo' => $this->estimateCostKobo($days),
        ]);
    }

    /**
     * @return array{checkout_url: ?string}
     */
    public function initiatePayment(AdCampaign $campaign, string $redirectUrl): array
    {
        if ($campaign->status !== 'pending_payment') {
            throw ValidationException::withMessages(['status' => 'This campaign has already been paid for.']);
        }

        $reference = 'AD-PAY-'.Str::upper(Str::random(14));
        $campaign->update(['payment_reference' => $reference]);

        $vendor = $campaign->vendor;

        $charge = $this->korapay->initializeCharge(
            reference: $reference,
            amountNaira: $campaign->amount_kobo / 100,
            customerEmail: $vendor->contact_email,
            customerName: $vendor->business_name,
            redirectUrl: $redirectUrl,
        );

        return ['checkout_url' => $charge['checkout_url'] ?? null];
    }

    /**
     * Called from KorapayWebhookController once the charge.success webhook lands — moves the
     * campaign to pending_approval and notifies admins. Still not visible to buyers: an admin must
     * approve it first (approve() below), which is what actually sets starts_at/ends_at.
     */
    public function markPaid(AdCampaign $campaign): void
    {
        if ($campaign->status !== 'pending_payment') {
            return;
        }

        $campaign->update(['status' => 'pending_approval', 'paid_at' => now()]);

        Notification::send(User::role(['admin', 'super_admin'])->get(), new NewAdCampaignSubmitted($campaign));
    }

    public function approve(AdCampaign $campaign, User $admin): void
    {
        if ($campaign->status !== 'pending_approval') {
            throw ValidationException::withMessages(['status' => 'Only a paid, pending campaign can be approved.']);
        }

        $now = now();

        // Respect the day the vendor chose at checkout (Vendor/Ads/Create.jsx) as long as it's
        // still ahead of us — a campaign approved after its requested start date just starts
        // immediately instead, rather than silently running short.
        $startsAt = $campaign->starts_at && $campaign->starts_at->isFuture() ? $campaign->starts_at : $now;

        $campaign->update([
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($campaign->duration_days),
            'approved_by' => $admin->id,
            'approved_at' => $now,
        ]);

        $campaign->vendor->user->notify(new AdCampaignApproved($campaign));
    }

    /**
     * Rejecting a campaign (e.g. an inappropriate banner) does NOT automatically refund the
     * vendor's payment — Korapay refunds are a deliberate, separate admin action (see
     * EscrowService::refund() for the existing pattern this would reuse), since not every rejection
     * reason warrants one (e.g. "please resubmit with a compliant banner" vs. a genuine mistake).
     */
    public function reject(AdCampaign $campaign, User $admin, string $reason): void
    {
        $campaign->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $campaign->vendor->user->notify(new AdCampaignRejected($campaign, $reason));
    }

    public function pause(AdCampaign $campaign): void
    {
        if ($campaign->status !== 'active') {
            throw ValidationException::withMessages(['status' => 'Only an active campaign can be paused.']);
        }

        $campaign->update(['status' => 'paused', 'paused_at' => now()]);
    }

    /**
     * Gated by a 24h cooldown since the last pause (AdCampaign::canResume()) — a vendor who could
     * pause/resume freely could effectively "save up" unused display time indefinitely instead of
     * the campaign running its paid-for window at a predictable pace.
     */
    public function resume(AdCampaign $campaign): void
    {
        if ($campaign->status !== 'paused') {
            throw ValidationException::withMessages(['status' => 'Only a paused campaign can be resumed.']);
        }

        if ($campaign->ends_at && $campaign->ends_at->isPast()) {
            throw ValidationException::withMessages(['status' => 'This campaign\'s paid duration has already ended.']);
        }

        if (! $campaign->canResume()) {
            $availableAt = $campaign->resumeAvailableAt()->format('g:ia \o\n d M Y');

            throw ValidationException::withMessages([
                'status' => "You can resume this campaign 24 hours after pausing it — available again at {$availableAt}.",
            ]);
        }

        $campaign->update(['status' => 'active', 'paused_at' => null]);
    }

    public function recordImpression(AdCampaign $campaign): void
    {
        $campaign->increment('impressions_count');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AdCampaign>  $campaigns
     */
    public function recordImpressions($campaigns): void
    {
        AdCampaign::whereIn('id', $campaigns->pluck('id'))->increment('impressions_count');
    }

    public function recordClick(AdCampaign $campaign): void
    {
        $campaign->increment('clicks_count');
    }

    public function recordDwell(AdCampaign $campaign, int $seconds): void
    {
        // A sanity cap — a stray/buggy beacon reporting hours of "dwell time" would otherwise
        // silently wreck the average for everyone looking at this campaign's metrics.
        $seconds = max(0, min($seconds, 1800));

        $campaign->increment('total_dwell_seconds', $seconds);
        $campaign->increment('dwell_samples_count');
    }

    /**
     * The actual "Top Picks" query — active, currently-in-window campaigns, one per vendor (a
     * vendor running back-to-back campaigns shouldn't crowd out others), randomized so campaigns
     * that started later still get fair rotation rather than always being pushed below older ones.
     */
    public function activeForDisplay(int $limit = 10)
    {
        return AdCampaign::query()
            ->currentlyLive()
            ->with('vendor.store')
            ->whereHas('vendor', fn ($q) => $q->where('vacation_mode', false)->where('status', 'active'))
            ->inRandomOrder()
            ->get()
            ->unique('vendor_id')
            ->take($limit)
            ->values();
    }
}
