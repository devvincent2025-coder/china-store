<?php

namespace App\Services\Export;

use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\Vendor\VendorAnalyticsService;

/**
 * Builds the full data set behind the "Business Report" export (PDF and Excel) — reuses
 * VendorAnalyticsService's own queries (see that class's doc comment: every method accepts an
 * optional Vendor, so passing null runs the identical query platform-wide instead of scoped to
 * one vendor) rather than duplicating them, so the report always matches what the dashboard shows.
 */
class ReportExportData
{
    public static function build(?Vendor $vendor): array
    {
        $analytics = app(VendorAnalyticsService::class);

        $data = [
            'scope' => $vendor ? 'vendor' : 'platform',
            'vendor' => $vendor,
            'summary' => $analytics->summaryStats($vendor),
            'monthOverMonth' => $analytics->monthOverMonthRevenue($vendor),
            'revenueOverTime' => $analytics->revenueOverTime($vendor, 30),
            'topProducts' => $analytics->topProducts($vendor, 10),
            'salesByLocation' => $analytics->salesByLocation($vendor),
            'orderStatusBreakdown' => $analytics->orderStatusBreakdown($vendor),
        ];

        if ($vendor) {
            $data['productCounts'] = [
                'total' => $vendor->products()->count(),
                'published' => $vendor->products()->where('status', 'published')->count(),
                'draft' => $vendor->products()->where('status', 'draft')->count(),
                'archived' => $vendor->products()->where('status', 'archived')->count(),
            ];

            return $data;
        }

        // Platform-wide extras — not meaningful per-vendor, so only added when $vendor is null.
        $data['marketplaceCounts'] = [
            'total_vendors' => Vendor::count(),
            'active_vendors' => Vendor::where('status', 'active')->count(),
            'total_products' => Product::count(),
            'published_products' => Product::where('status', 'published')->count(),
            'total_buyers' => Order::query()->distinct('user_id')->count('user_id'),
        ];

        // Top-earning vendors (by payout_amount on paid orders) — the platform-wide equivalent of
        // "top products" for a single vendor's own report.
        $data['topVendors'] = Vendor::query()
            ->select('vendors.id', 'vendors.business_name')
            ->join('vendor_orders', 'vendor_orders.vendor_id', '=', 'vendors.id')
            ->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw('SUM(vendor_orders.payout_amount) as revenue, COUNT(*) as orders')
            ->groupBy('vendors.id', 'vendors.business_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'business_name' => $row->business_name,
                'revenue' => (int) $row->revenue,
                'orders' => (int) $row->orders,
            ])
            ->all();

        return $data;
    }
}
