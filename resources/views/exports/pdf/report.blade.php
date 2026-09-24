@extends('exports.pdf.layout')

@section('content')
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Orders</div>
            <div class="summary-value">{{ number_format($data['summary']['total_orders']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Revenue</div>
            <div class="summary-value">{{ \App\Support\Money::naira($data['summary']['total_revenue']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Avg. Order Value</div>
            <div class="summary-value">{{ \App\Support\Money::naira($data['summary']['average_order_value']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Customers</div>
            <div class="summary-value">{{ number_format($data['summary']['total_customers']) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Repeat Rate</div>
            <div class="summary-value">{{ number_format($data['summary']['repeat_customer_rate'], 1) }}%</div>
        </div>
    </div>

    <div class="section-heading">This Month vs Last Month</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>This Month</th>
                <th>Last Month</th>
                <th>Growth</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ \App\Support\Money::naira($data['monthOverMonth']['current_month']) }}</td>
                <td>{{ \App\Support\Money::naira($data['monthOverMonth']['previous_month']) }}</td>
                <td>{{ $data['monthOverMonth']['growth_percent'] === null ? 'New' : number_format($data['monthOverMonth']['growth_percent'], 1).'%' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($data['scope'] === 'platform')
        <div class="section-heading">Marketplace Overview</div>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Vendors</div>
                <div class="summary-value">{{ number_format($data['marketplaceCounts']['total_vendors']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Active Vendors</div>
                <div class="summary-value">{{ number_format($data['marketplaceCounts']['active_vendors']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Products</div>
                <div class="summary-value">{{ number_format($data['marketplaceCounts']['total_products']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Published</div>
                <div class="summary-value">{{ number_format($data['marketplaceCounts']['published_products']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Buyers</div>
                <div class="summary-value">{{ number_format($data['marketplaceCounts']['total_buyers']) }}</div>
            </div>
        </div>

        <div class="section-heading">Top Vendors by Revenue</div>
        <table class="data-table">
            <thead>
                <tr><th>Vendor</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
            </thead>
            <tbody>
                @forelse ($data['topVendors'] as $row)
                    <tr>
                        <td>{{ $row['business_name'] }}</td>
                        <td class="text-right">{{ number_format($row['orders']) }}</td>
                        <td class="text-right">{{ \App\Support\Money::naira($row['revenue']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">No vendor sales yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <div class="section-heading">Product Catalog</div>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Products</div>
                <div class="summary-value">{{ number_format($data['productCounts']['total']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Published</div>
                <div class="summary-value">{{ number_format($data['productCounts']['published']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Draft</div>
                <div class="summary-value">{{ number_format($data['productCounts']['draft']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Archived</div>
                <div class="summary-value">{{ number_format($data['productCounts']['archived']) }}</div>
            </div>
        </div>
    @endif

    <div class="section-heading">Top Products by Units Sold</div>
    <table class="data-table">
        <thead>
            <tr><th>Product</th><th class="text-right">Units Sold</th><th class="text-right">Revenue</th></tr>
        </thead>
        <tbody>
            @forelse ($data['topProducts'] as $row)
                <tr>
                    <td>{{ $row['title'] }}</td>
                    <td class="text-right">{{ number_format($row['quantity']) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted">No product sales yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-heading">Fulfillment Status Breakdown</div>
    <table class="data-table">
        <thead>
            <tr><th>Processing</th><th>Shipped</th><th>Delivered</th><th>Cancelled</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ number_format($data['orderStatusBreakdown']['processing']) }}</td>
                <td>{{ number_format($data['orderStatusBreakdown']['shipped']) }}</td>
                <td>{{ number_format($data['orderStatusBreakdown']['delivered']) }}</td>
                <td>{{ number_format($data['orderStatusBreakdown']['cancelled']) }}</td>
            </tr>
        </tbody>
    </table>

    @if (count($data['salesByLocation']) > 0)
        <div class="section-heading">Sales by State</div>
        <table class="data-table">
            <thead>
                <tr><th>State</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
            </thead>
            <tbody>
                @foreach ($data['salesByLocation'] as $row)
                    <tr>
                        <td>{{ $row['state'] }}</td>
                        <td class="text-right">{{ number_format($row['orders']) }}</td>
                        <td class="text-right">{{ \App\Support\Money::naira($row['revenue']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-heading">Revenue — Last 30 Days</div>
    <table class="data-table">
        <thead>
            <tr><th>Date</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr>
        </thead>
        <tbody>
            @foreach ($data['revenueOverTime'] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td class="text-right">{{ number_format($row['orders']) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($row['revenue']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
