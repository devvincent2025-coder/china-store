<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\Ads\AdCampaignService;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, AdCampaignService $ads): Response
    {
        $published = fn ($q) => $q->publiclyListed()->with(['images', 'vendor.store']);

        // "Top Picks — Recommended for you" — paid vendor placements, one per vendor. Recording
        // the impression here (rather than needing a viewport-visibility beacon) is a deliberate
        // simplification: every buyer who loads the homepage counts as having seen these cards.
        $topPicks = $ads->activeForDisplay(10);
        $ads->recordImpressions($topPicks);

        $dealProducts = Product::query()
            ->tap($published)
            ->where('is_featured', true)
            ->latest()
            ->limit(4)
            ->get();

        // If fewer than 4 featured, fill from trending so the subsidy grid never looks empty.
        if ($dealProducts->count() < 4) {
            $extra = Product::query()
                ->tap($published)
                ->whereNotIn('id', $dealProducts->pluck('id'))
                ->orderByDesc('views_count')
                ->limit(4 - $dealProducts->count())
                ->get();
            $dealProducts = $dealProducts->concat($extra);
        }

        $pool = Product::query()
            ->tap($published)
            ->orderByDesc('views_count')
            ->limit(40)
            ->get();

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

            'dealProducts' => $dealProducts->values(),
            'liveProducts' => $pool->slice(0, 2)->values(),
            'factoryProducts' => $pool->slice(2, 2)->values(),
            'budgetProducts' => $pool->slice(4, 2)->values(),

            'recommendedProducts' => Product::query()
                ->tap($published)
                ->inRandomOrder()
                ->limit(30)
                ->get(),

            'seo' => Seo::forHome($request),
        ]);
    }
}
