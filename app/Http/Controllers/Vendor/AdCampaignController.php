<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Services\Ads\AdCampaignService;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdCampaignController extends Controller
{
    public function __construct(
        private readonly AdCampaignService $ads,
        private readonly KorapayService $korapay,
    ) {
    }

    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;

        $campaigns = $vendor->adCampaigns()->latest()->get()->map(fn (AdCampaign $c) => [
            ...$c->toArray(),
            'click_through_rate' => $c->clickThroughRate(),
            'average_dwell_seconds' => $c->averageDwellSeconds(),
            'can_resume' => $c->canResume(),
            'resume_available_at' => $c->resumeAvailableAt()?->toIso8601String(),
        ]);

        return Inertia::render('Vendor/Ads/Index', [
            'vendor' => $vendor,
            'campaigns' => $campaigns,
            'hasActiveOrPending' => $vendor->adCampaigns()->whereIn('status', ['pending_payment', 'pending_approval', 'active', 'paused'])->exists(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Vendor/Ads/Create', [
            'vendor' => $request->user()->vendor,
            'costPerDayKobo' => $this->ads->costPerDayKobo(),
            'minDays' => $this->ads->minDays(),
            'maxDays' => $this->ads->maxDays(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'duration_days' => ['required', 'integer'],
            'banner' => ['nullable', 'image', 'max:5120'],
            'starts_at' => ['required', 'date', 'after_or_equal:today'],
            'terms_accepted' => ['accepted'],
        ]);

        $vendor = $request->user()->vendor;

        $campaign = $this->ads->create(
            $vendor,
            (int) $validated['duration_days'],
            $request->file('banner'),
            Carbon::parse($validated['starts_at']),
        );

        $result = $this->ads->initiatePayment($campaign, route('vendor.ads.callback', $campaign));

        // Korapay's checkout page is a different origin — Inertia::location() forces a real
        // browser navigation instead of being followed as an XHR request. See
        // InvoicePaymentController::store() for the same pattern used elsewhere in this app.
        return Inertia::location($result['checkout_url'] ?? route('vendor.ads.index'));
    }

    /**
     * Korapay redirects the vendor's browser here after paying. The webhook (see
     * Http\Controllers\Webhooks\KorapayWebhookController) is the primary confirmation path, but it
     * can only ever reach a publicly reachable webhook URL — in local development (and any
     * environment Korapay's servers can't reach) it never arrives at all. So this also actively
     * verifies the charge via Korapay's API as a synchronous fallback, exactly as
     * KorapayService::verifyCharge()'s own doc comment describes but which nothing previously
     * called. markPaid() is idempotent (it no-ops if the webhook already moved the campaign along),
     * so running both paths is safe.
     */
    public function callback(AdCampaign $adCampaign): RedirectResponse
    {
        $this->authorize('update', $adCampaign);

        if ($adCampaign->status === 'pending_payment' && $adCampaign->payment_reference) {
            try {
                $result = $this->korapay->verifyCharge($adCampaign->payment_reference);

                if (($result['status'] ?? null) === 'success') {
                    $this->ads->markPaid($adCampaign);
                }
            } catch (KorapayRequestException $e) {
                // Not fatal — the webhook may still land shortly, or the vendor can reload this
                // page later; either way we don't want to block them from returning to their
                // campaigns list just because this fallback check failed.
                Log::warning('Ad campaign payment verification failed on callback', [
                    'ad_campaign_id' => $adCampaign->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $message = $adCampaign->fresh()->status === 'pending_payment'
            ? 'Payment is still processing — this page will update automatically once it clears.'
            : 'Payment received — your campaign is awaiting admin approval.';

        return redirect()->route('vendor.ads.index')->with('success', $message);
    }

    public function pause(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        $this->authorize('update', $adCampaign);

        try {
            $this->ads->pause($adCampaign);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Campaign paused. You can resume it 24 hours from now.');
    }

    public function resume(Request $request, AdCampaign $adCampaign): RedirectResponse
    {
        $this->authorize('update', $adCampaign);

        try {
            $this->ads->resume($adCampaign);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with('success', 'Campaign resumed.');
    }
}
