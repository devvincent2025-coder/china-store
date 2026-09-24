<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Support\Carbon;

/**
 * Business-intelligence queries for the vendor dashboard — every method here scopes to ONE vendor's
 * own data and only counts orders that were actually paid (payment_status = 'paid'), since a
 * pending/failed order was never real revenue. All money returned is in kobo, matching the rest of
 * the app's convention; the frontend formats it via formatNaira().
 *
 * Every method takes an OPTIONAL $vendor — passing null runs the exact same query platform-wide
 * instead of scoped to one vendor, which is what the admin-side marketplace report (Services\
 * Export\ReportExportData) reuses this class for, rather than duplicating each query.
 */
class VendorAnalyticsService
{
    private function vendorOrders(?Vendor $vendor)
    {
        return $vendor ? $vendor->vendorOrders() : VendorOrder::query();
    }
    /**
     * Daily payout total for the last N days, including zero-filled days with no sales, so a line
     * chart doesn't have misleading gaps.
     *
     * @return array<int, array{date: string, revenue: int, orders: int}>
     */
    public function revenueOverTime(?Vendor $vendor, int $days = 30): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = $this->vendorOrders($vendor)
            ->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.paid_at', '>=', $from)
            ->selectRaw('DATE(orders.paid_at) as date, SUM(vendor_orders.payout_amount) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $row = $rows->get($date);

            $series[] = [
                'date' => $date,
                'revenue' => (int) ($row->revenue ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Top N products by units sold (paid orders only) — the pie/donut chart's data source.
     *
     * @return array<int, array{product_id: ?int, title: string, quantity: int, revenue: int}>
     */
    public function topProducts(?Vendor $vendor, int $limit = 5): array
    {
        return $this->vendorOrders($vendor)
            ->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->join('order_items', 'order_items.vendor_order_id', '=', 'vendor_orders.id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw('
                order_items.product_id,
                order_items.product_title_snapshot,
                SUM(order_items.quantity) as quantity,
                SUM(order_items.line_total) as revenue
            ')
            ->groupBy('order_items.product_id', 'order_items.product_title_snapshot')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                // product_title_snapshot is a translatable JSON object {"en":...,"zh":...,"fr":...}
                // stored on the order_item itself, since the live product may since have changed.
                'title' => json_decode($row->product_title_snapshot, true)['en'] ?? 'Unknown product',
                'quantity' => (int) $row->quantity,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Order count + revenue per Nigerian state, sourced from each order's shipping_address JSON —
     * feeds the "sales by location" heatmap. States with zero orders are omitted (the frontend
     * heatmap only needs to plot what actually has data).
     *
     * @return array<int, array{state: string, orders: int, revenue: int}>
     */
    public function salesByLocation(?Vendor $vendor): array
    {
        return $this->vendorOrders($vendor)
            ->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->where('orders.payment_status', 'paid')
            ->selectRaw('
                JSON_UNQUOTE(JSON_EXTRACT(orders.shipping_address, "$.state")) as state,
                COUNT(*) as orders,
                SUM(vendor_orders.payout_amount) as revenue
            ')
            ->groupBy('state')
            ->havingRaw('state IS NOT NULL')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'state' => $row->state,
                'orders' => (int) $row->orders,
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Fulfillment status counts across ALL of this vendor's orders (not just paid — an order only
     * exists as a vendor_order once payment succeeded in the first place, per CheckoutService, so
     * no separate payment filter is needed here).
     *
     * @return array<string, int>
     */
    public function orderStatusBreakdown(?Vendor $vendor): array
    {
        $counts = $this->vendorOrders($vendor)
            ->selectRaw('fulfillment_status, COUNT(*) as total')
            ->groupBy('fulfillment_status')
            ->pluck('total', 'fulfillment_status');

        return [
            'processing' => (int) ($counts['processing'] ?? 0),
            'shipped' => (int) ($counts['shipped'] ?? 0),
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    /**
     * Headline KPI cards: order volume, revenue, average order value, distinct customers, and a
     * repeat-purchase rate — the last one is a genuine signal of customer loyalty a vendor can act on.
     *
     * @return array{total_orders: int, total_revenue: int, average_order_value: int, total_customers: int, repeat_customer_rate: float, low_stock_count: int}
     */
    public function summaryStats(?Vendor $vendor): array
    {
        $paidOrders = $this->vendorOrders($vendor)
            ->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->where('orders.payment_status', 'paid');

        $totalOrders = (clone $paidOrders)->count();
        $totalRevenue = (int) (clone $paidOrders)->sum('vendor_orders.payout_amount');
        $averageOrderValue = $totalOrders > 0 ? intdiv($totalRevenue, $totalOrders) : 0;

        $customerCounts = (clone $paidOrders)
            ->select('orders.user_id')
            ->groupBy('orders.user_id')
            ->selectRaw('COUNT(*) as order_count')
            ->get();

        $totalCustomers = $customerCounts->count();
        $repeatCustomers = $customerCounts->filter(fn ($row) => $row->order_count > 1)->count();
        $repeatCustomerRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0.0;

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $averageOrderValue,
            'total_customers' => $totalCustomers,
            'repeat_customer_rate' => $repeatCustomerRate,
            // Only meaningful per-vendor (each vendor sets their own threshold) — platform-wide
            // reports omit it entirely rather than mixing thresholds across vendors.
            'low_stock_count' => $vendor?->products()->where('stock', '>', 0)->where('stock', '<=', $vendor->low_stock_threshold)->count(),
        ];
    }

    /**
     * Revenue for the current and previous full calendar month, so the dashboard can show a
     * month-over-month growth percentage — a vendor's most immediate "am I growing?" signal.
     *
     * @return array{current_month: int, previous_month: int, growth_percent: ?float}
     */
    public function monthOverMonthRevenue(?Vendor $vendor): array
    {
        $base = $this->vendorOrders($vendor)->join('orders', 'orders.id', '=', 'vendor_orders.order_id')
            ->where('orders.payment_status', 'paid');

        $currentMonth = (int) (clone $base)
            ->whereBetween('orders.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('vendor_orders.payout_amount');

        $previousMonth = (int) (clone $base)
            ->whereBetween('orders.paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('vendor_orders.payout_amount');

        $growth = $previousMonth > 0
            ? round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1)
            : null; // No prior-month baseline to compare against — the frontend shows "New" instead of a %.

        return [
            'current_month' => $currentMonth,
            'previous_month' => $previousMonth,
            'growth_percent' => $growth,
        ];
    }
}
