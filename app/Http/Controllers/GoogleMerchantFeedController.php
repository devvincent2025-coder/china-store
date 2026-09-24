<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * A Google Merchant Center-compatible product feed (RSS 2.0 + the `g:` namespace Google's spec
 * requires) — this is what actually gets a product's image + price shown directly in Google
 * Search/Shopping results ("free listings"), which plain Product structured data alone only makes
 * *eligible* for as an organic rich snippet. Submitting this feed's URL to a Merchant Center
 * account is a manual one-time setup step outside this codebase; the feed itself needs no such
 * account to exist and to already be correct.
 */
class GoogleMerchantFeedController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('feeds.google-merchant', 21600, function () {
            $products = Product::query()
                ->publiclyListed()
                ->with(['images', 'vendor.store', 'category'])
                ->get();

            return view('feeds.google-merchant', ['products' => $products])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
