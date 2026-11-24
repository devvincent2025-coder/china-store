<?php

namespace App\Http\Controllers;

use App\Models\VendorStore;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorStoreController extends Controller
{
    public function index(Request $request): Response
    {
        $stores = VendorStore::query()
            ->with(['vendor', 'category'])
            ->whereHas('vendor', fn ($q) => $q->where('status', 'active'))
            ->orderBy('slug')
            ->paginate(24);

        return Inertia::render('Stores/Index', [
            'stores' => $stores,
            'seo' => Seo::generic(
                $request,
                'Verified Chinese-Run Stores in Nigeria',
                'Browse real Chinese-run vendor stores selling in Nigeria — verified sellers, Naira pricing, fast local delivery, and every order protected by escrow until you confirm you\'re happy.',
                ['chinese stores in nigeria', 'chinese vendors nigeria', 'chinese sellers nigeria', 'verified chinese stores', 'buy from chinese vendors nigeria']
            ),
        ]);
    }

    public function show(Request $request, VendorStore $vendorStore): Response
    {
        $vendorStore->load(['vendor', 'category']);

        // On vacation, or the vendor isn't currently active (suspended/rejected — see
        // Services\Vendor\VendorSuspensionService): the store page itself still loads (with a
        // banner — see Vendors/Show.jsx) rather than 404ing, but no products are listed and it's
        // excluded from every other public listing via Product::scopePubliclyListed().
        $isUnavailable = $vendorStore->vendor->vacation_mode || $vendorStore->vendor->status !== 'active';

        $products = $isUnavailable
            ? $vendorStore->vendor->products()->whereRaw('1 = 0')->paginate(24)
            : $vendorStore->vendor->products()->where('status', 'published')->with('images')->paginate(24);

        $banners = $vendorStore->vendor->banners()
            ->where('placement', 'store')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Vendors/Show', [
            'store' => $vendorStore,
            'products' => $products,
            'banners' => $banners,
            'onVacation' => $vendorStore->vendor->vacation_mode,
            'vacationMessage' => $vendorStore->vendor->vacation_message,
            'storeSuspended' => $vendorStore->vendor->status !== 'active' && ! $vendorStore->vendor->vacation_mode,
            // Vendor-specific coupons currently redeemable — the only place these were ever
            // visible to a buyer before this was the checkout coupon box itself (if they already
            // knew the code). Platform-wide coupons are shown on the homepage instead (see
            // HomeController).
            'coupons' => $vendorStore->vendor->coupons()->currentlyValid()->get(['code', 'type', 'value', 'minimum_spend']),
            'seo' => Seo::forVendorStore($request, $vendorStore),
        ]);
    }
}
