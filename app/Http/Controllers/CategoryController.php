<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Ads\AdCampaignService;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category, AdCampaignService $ads): Response
    {
        $products = $category->products()
            ->publiclyListed()
            ->with(['images', 'vendor.store'])
            ->orderByDesc('views_count')
            ->paginate(24)
            ->withQueryString();

        $topPicks = $ads->activeForDisplay(5);
        $ads->recordImpressions($topPicks);

        return Inertia::render('Categories/Show', [
            'category' => $category,
            'subcategories' => $category->children()->where('is_active', true)->get(),
            'products' => $products,
            'topPicks' => $topPicks->map(fn ($campaign) => [
                'token' => $campaign->token,
                'banner_url' => $campaign->banner_url,
                'store' => $campaign->vendor->store,
            ]),
            'seo' => Seo::forCategory($request, $category),
        ]);
    }
}
