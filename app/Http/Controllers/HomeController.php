<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Services\Ads\AdCampaignService;
use App\Services\Home\HomePageBuilder;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, AdCampaignService $ads, HomePageBuilder $builder): Response
    {
        // "Top Picks — Recommended for you" — paid vendor placements, one per vendor. Recording
        // the impression here (rather than needing a viewport-visibility beacon) is a deliberate
        // simplification: every buyer who loads the homepage counts as having seen these cards.
        $topPicks = $ads->activeForDisplay(10);
        $ads->recordImpressions($topPicks);

        return Inertia::render('Home', [
            'topPicks' => $topPicks->map(fn ($campaign) => [
                'token' => $campaign->token,
                'banner_url' => $campaign->banner_url,
                'store' => $campaign->vendor->store,
            ]),

            'banners' => Banner::query()
                ->where('placement', 'homepage')
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->orderBy('sort_order')
                ->get(),

            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),

            'coupons' => Coupon::query()
                ->whereNull('vendor_id')
                ->currentlyValid()
                ->orderBy('code')
                ->limit(3)
                ->get(['code', 'type', 'value']),

            // Every block's title, order, visibility, products and deal prices come from the
            // admin-managed home_sections rows — see App\Services\Home\HomePageBuilder.
            'sections' => $builder->build(),

            'seo' => Seo::forHome($request),
        ]);
    }
}
