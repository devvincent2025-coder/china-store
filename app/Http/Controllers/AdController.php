<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Services\Ads\AdCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public (no auth) — every buyer who sees a "Top Picks" ad card, logged in or not, can visit it or
 * generate a dwell-time sample. Both routes are keyed by AdCampaign::token (an opaque 20-char
 * random string — see AdCampaign::booted()), never the raw numeric id, and the path/param naming
 * here is deliberately generic rather than spelling out "ads"/"click" in the URL.
 */
class AdController extends Controller
{
    public function __construct(private readonly AdCampaignService $ads)
    {
    }

    /**
     * The href every ad card actually points to — records the click, then redirects to the
     * vendor's store page with ?ref={token} appended so Vendors/Show.jsx knows to start a
     * dwell-time beacon for this visit.
     */
    public function visit(AdCampaign $adCampaign): RedirectResponse
    {
        if ($adCampaign->status !== 'active') {
            return redirect()->route('home');
        }

        $this->ads->recordClick($adCampaign);

        $store = $adCampaign->vendor->store;

        if (! $store) {
            return redirect()->route('home');
        }

        return redirect()->route('vendors.show', [$store->slug, 'ref' => $adCampaign->token]);
    }

    /**
     * Fired via navigator.sendBeacon when a buyer who arrived via an ad leaves the vendor's store
     * page — see Vendors/Show.jsx. Deliberately tolerant of being called late/never (a closed tab
     * without a beacon just means that one visit contributes no dwell sample, not an error).
     */
    public function ping(Request $request, AdCampaign $adCampaign): JsonResponse
    {
        $validated = $request->validate(['seconds' => ['required', 'integer', 'min:0']]);

        $this->ads->recordDwell($adCampaign, (int) $validated['seconds']);

        return response()->json(['ok' => true]);
    }
}
