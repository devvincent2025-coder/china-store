<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, VendorAnalyticsService $analytics): Response
    {
        $vendor = $request->user()->vendor;

        return Inertia::render('Vendor/Dashboard', [
            'vendor' => $vendor,
            // Drives the "Boost your store" dashboard banner — hidden once a campaign already
            // exists in any non-final state, so a vendor isn't nagged to buy a second one.
            'hasAdCampaign' => $vendor->adCampaigns()->whereNotIn('status', ['rejected', 'expired', 'cancelled'])->exists(),
            // One-time celebratory modal (Components/VendorKycApprovedModal.jsx) the first time a
            // vendor loads the dashboard after an admin approves their identity — see
            // Vendor::shouldShowKycCelebration()'s doc comment for why this can't just be a flash.
            'kycJustApproved' => $vendor->shouldShowKycCelebration(),
            'stats' => [
                'total_products' => $vendor->products()->count(),
                'published_products' => $vendor->products()->where('status', 'published')->count(),
                'pending_orders' => $vendor->vendorOrders()->where('fulfillment_status', 'processing')->count(),
                'total_revenue' => $vendor->vendorOrders()->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))->sum('payout_amount'),
                'active_coupons' => $vendor->coupons()->where('is_active', true)->count(),
            ],
            'recentOrders' => $vendor->vendorOrders()->with('order', 'items')->latest()->limit(5)->get(),

            // Business-intelligence data for the analytics section — see VendorAnalyticsService for
            // the query logic behind each of these.
            'analytics' => [
                'revenueOverTime' => $analytics->revenueOverTime($vendor, 30),
                'topProducts' => $analytics->topProducts($vendor, 5),
                'salesByLocation' => $analytics->salesByLocation($vendor),
                'orderStatusBreakdown' => $analytics->orderStatusBreakdown($vendor),
                'summary' => $analytics->summaryStats($vendor),
                'monthOverMonth' => $analytics->monthOverMonthRevenue($vendor),
            ],
        ]);
    }
}
