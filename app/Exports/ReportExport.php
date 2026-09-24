<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Multi-sheet workbook version of exports/pdf/report.blade.php — same underlying $data from
 * App\Services\Export\ReportExportData, just one sheet per section instead of stacked on one page.
 */
class ReportExport implements Export, WithMultipleSheets
{
    public function __construct(private readonly array $data)
    {
    }

    public function sheets(): array
    {
        $d = $this->data;

        $summaryRows = [
            ['Total Orders', $d['summary']['total_orders']],
            ['Total Revenue (₦)', $d['summary']['total_revenue'] / 100],
            ['Average Order Value (₦)', $d['summary']['average_order_value'] / 100],
            ['Total Customers', $d['summary']['total_customers']],
            ['Repeat Customer Rate (%)', $d['summary']['repeat_customer_rate']],
            ['This Month Revenue (₦)', $d['monthOverMonth']['current_month'] / 100],
            ['Last Month Revenue (₦)', $d['monthOverMonth']['previous_month'] / 100],
            ['Growth (%)', $d['monthOverMonth']['growth_percent'] ?? 'New'],
        ];

        if ($d['scope'] === 'platform') {
            $summaryRows = [
                ...$summaryRows,
                ['Total Vendors', $d['marketplaceCounts']['total_vendors']],
                ['Active Vendors', $d['marketplaceCounts']['active_vendors']],
                ['Total Products', $d['marketplaceCounts']['total_products']],
                ['Published Products', $d['marketplaceCounts']['published_products']],
                ['Total Buyers', $d['marketplaceCounts']['total_buyers']],
            ];
        } else {
            $summaryRows = [
                ...$summaryRows,
                ['Total Products', $d['productCounts']['total']],
                ['Published Products', $d['productCounts']['published']],
                ['Draft Products', $d['productCounts']['draft']],
                ['Archived Products', $d['productCounts']['archived']],
            ];
        }

        $sheets = [
            new ArraySheet('Summary', ['Metric', 'Value'], $summaryRows),
            new ArraySheet(
                'Revenue (Last 30 Days)',
                ['Date', 'Orders', 'Revenue (₦)'],
                collect($d['revenueOverTime'])->map(fn ($r) => [$r['date'], $r['orders'], $r['revenue'] / 100])->all(),
            ),
            new ArraySheet(
                'Top Products',
                ['Product', 'Units Sold', 'Revenue (₦)'],
                collect($d['topProducts'])->map(fn ($r) => [$r['title'], $r['quantity'], $r['revenue'] / 100])->all(),
            ),
            new ArraySheet(
                'Fulfillment Breakdown',
                ['Status', 'Count'],
                collect($d['orderStatusBreakdown'])->map(fn ($count, $status) => [ucfirst($status), $count])->values()->all(),
            ),
            new ArraySheet(
                'Sales By State',
                ['State', 'Orders', 'Revenue (₦)'],
                collect($d['salesByLocation'])->map(fn ($r) => [$r['state'], $r['orders'], $r['revenue'] / 100])->all(),
            ),
        ];

        if ($d['scope'] === 'platform') {
            $sheets[] = new ArraySheet(
                'Top Vendors',
                ['Vendor', 'Orders', 'Revenue (₦)'],
                collect($d['topVendors'])->map(fn ($r) => [$r['business_name'], $r['orders'], $r['revenue'] / 100])->all(),
            );
        }

        return $sheets;
    }
}
