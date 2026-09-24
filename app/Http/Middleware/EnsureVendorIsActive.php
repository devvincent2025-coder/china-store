<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a vendor from reaching their product/order/coupon dashboard until onboarding is fully
 * complete (documents approved + bank verified + Korapay subaccount provisioned) — everything up
 * to that point only needs the /vendor/onboarding routes.
 */
class EnsureVendorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $vendor = $request->user()?->vendor;

        if (! $vendor || $vendor->status !== 'active') {
            return redirect()->route('vendor.onboarding')
                ->with('warning', 'Please finish onboarding before accessing your dashboard.');
        }

        return $next($request);
    }
}
