<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpenShopController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        // A vendor who's already registered has nothing to gain from the "become a vendor" pitch —
        // send them straight to their own dashboard instead (which itself redirects to onboarding
        // if their store isn't active yet, via the vendor.active middleware).
        if ($request->user()?->isVendor()) {
            return redirect()->route('vendor.dashboard');
        }

        return Inertia::render('OpenShop/Index');
    }
}
