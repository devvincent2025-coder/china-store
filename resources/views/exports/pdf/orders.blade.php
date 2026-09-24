@extends('exports.pdf.layout')

@section('content')
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Orders</div>
            <div class="summary-value">{{ number_format($orders->count()) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Value</div>
            <div class="summary-value">{{ \App\Support\Money::naira($orders->sum('total')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Payout</div>
            <div class="summary-value">{{ \App\Support\Money::naira($orders->sum('payout_amount')) }}</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">With Coupon</div>
            <div class="summary-value">{{ number_format($orders->whereNotNull('coupon_id')->count()) }}</div>
        </div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Buyer</th>
                @if (! $vendor)
                    <th>Vendor</th>
                @endif
                <th class="text-right">Total</th>
                <th class="text-right">Commission</th>
                <th class="text-right">Payout</th>
                <th>Escrow</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $vendorOrder)
                <tr>
                    <td>{{ $vendorOrder->order?->order_number }}</td>
                    <td>{{ $vendorOrder->created_at->format('d M Y') }}</td>
                    <td>{{ $vendorOrder->order?->user?->name }}</td>
                    @if (! $vendor)
                        <td>{{ $vendorOrder->vendor?->business_name }}</td>
                    @endif
                    <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->total) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->commission_amount) }}</td>
                    <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->payout_amount) }}</td>
                    <td>
                        <span class="badge {{ match($vendorOrder->escrow_status) {
                            'released' => 'badge-green',
                            'holding' => 'badge-blue',
                            'releasing', 'refund_pending' => 'badge-amber',
                            'refunded' => 'badge-red',
                            default => 'badge-gray',
                        } }}">{{ str_replace('_', ' ', $vendorOrder->escrow_status) }}</span>
                    </td>
                    <td>
                        <span class="badge {{ match($vendorOrder->fulfillment_status) {
                            'delivered' => 'badge-green',
                            'shipped' => 'badge-blue',
                            'processing' => 'badge-amber',
                            'cancelled' => 'badge-red',
                            default => 'badge-gray',
                        } }}">{{ $vendorOrder->fulfillment_status }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $vendor ? 7 : 8 }}" class="text-muted">No orders found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
