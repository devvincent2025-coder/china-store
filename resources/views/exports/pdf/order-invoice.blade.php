@extends('exports.pdf.layout', [
    'title' => 'INVOICE',
    'subtitle' => 'Order #'.$order->order_number,
    'preparedFor' => $order->user->name,
])

@php
    $titleFor = fn ($item) => $item->product_title_snapshot[app()->getLocale()]
        ?? $item->product_title_snapshot['en']
        ?? (is_array($item->product_title_snapshot) ? reset($item->product_title_snapshot) : 'Product');

    $statusBadge = match ($order->payment_status) {
        'paid' => ['badge-green', 'Paid'],
        'pending' => ['badge-amber', 'Pending'],
        'failed' => ['badge-red', 'Payment Failed'],
        default => ['badge-gray', ucfirst($order->payment_status)],
    };
@endphp

@section('content')
    <style>
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 22px;
        }

        .invoice-info .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .info-heading {
            font-size: 9px;
            font-weight: bold;
            color: #999999;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .info-line {
            font-size: 10.5px;
            color: #333333;
            line-height: 1.6;
        }

        .vendor-block {
            margin-bottom: 18px;
            border: 1px solid #eeeeee;
            border-radius: 4px;
        }

        .vendor-heading {
            background: #fafafa;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: bold;
            color: #18181b;
            border-bottom: 1px solid #eeeeee;
        }

        .vendor-heading .vendor-total {
            float: right;
            color: #FF5000;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.items-table th {
            background: #ffffff;
            color: #999999;
            text-align: left;
            padding: 6px 10px;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #eeeeee;
        }

        table.items-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #f5f5f5;
            font-size: 9.5px;
        }

        table.items-table tfoot td {
            border-bottom: none;
            padding-top: 5px;
            font-size: 9.5px;
        }

        table.items-table tfoot tr.grand td {
            padding-top: 8px;
            border-top: 1px solid #eeeeee;
            font-size: 11px;
            font-weight: bold;
            color: #18181b;
        }

        .totals-box {
            margin-top: 6px;
            width: 260px;
            float: right;
        }

        .totals-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-box td {
            padding: 4px 0;
            font-size: 10px;
            color: #555555;
        }

        .totals-box td.amount {
            text-align: right;
            color: #18181b;
        }

        .totals-box tr.grand td {
            border-top: 2px solid #FF5000;
            padding-top: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #18181b;
        }

        .totals-box tr.grand td.amount {
            color: #FF5000;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .protection-note {
            margin-top: 20px;
            padding: 10px 12px;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: 4px;
            font-size: 9px;
            color: #15803d;
            line-height: 1.5;
        }
    </style>

    <div class="invoice-info">
        <div class="info-col">
            <div class="info-heading">Billed To</div>
            <div class="info-line">
                {{ $order->user->name }}<br>
                {{ $order->user->email }}<br>
                @if ($order->shipping_address)
                    {{ $order->shipping_address['phone'] ?? '' }}
                @endif
            </div>
        </div>
        <div class="info-col">
            <div class="info-heading">Ship To</div>
            <div class="info-line">
                @if ($order->shipping_address)
                    {{ $order->shipping_address['name'] ?? '' }}<br>
                    {{ $order->shipping_address['line1'] ?? '' }}<br>
                    {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }}
                @endif
            </div>
        </div>
    </div>

    <div class="invoice-info">
        <div class="info-col">
            <div class="info-heading">Order Date</div>
            <div class="info-line">{{ $order->created_at->format('d M Y, h:i A') }}</div>
        </div>
        <div class="info-col">
            <div class="info-heading">Payment Status</div>
            <div class="info-line">
                <span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                @if ($order->paid_at)
                    &nbsp; Paid on {{ $order->paid_at->format('d M Y') }}
                @endif
            </div>
        </div>
    </div>

    @foreach ($order->vendorOrders as $vendorOrder)
        <div class="vendor-block">
            <div class="vendor-heading clearfix">
                {{ $vendorOrder->vendor->store?->name ?? $vendorOrder->vendor->business_name }}
                <span class="vendor-total">{{ \App\Support\Money::naira($vendorOrder->total) }}</span>
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendorOrder->items as $item)
                        <tr>
                            <td>
                                {{ $titleFor($item) }}
                                @if (! empty($item->variant_label_snapshot))
                                    <br><span class="text-muted" style="font-size: 8.5px;">
                                        {{ collect($item->variant_label_snapshot)->map(fn ($pair) => ($pair['option'][app()->getLocale()] ?? $pair['option']['en'] ?? '').': '.($pair['value'][app()->getLocale()] ?? $pair['value']['en'] ?? ''))->join(' · ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">{{ $item->quantity }}</td>
                            <td class="text-right">{{ \App\Support\Money::naira($item->unit_price) }}</td>
                            <td class="text-right">{{ \App\Support\Money::naira($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right text-muted">Subtotal</td>
                        <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->subtotal) }}</td>
                    </tr>
                    @if ($vendorOrder->discount > 0)
                        <tr>
                            <td colspan="3" class="text-right text-muted">
                                Discount @if ($vendorOrder->coupon)({{ $vendorOrder->coupon->code }})@endif
                            </td>
                            <td class="text-right">&minus;{{ \App\Support\Money::naira($vendorOrder->discount) }}</td>
                        </tr>
                    @endif
                    @if ($vendorOrder->vat_amount > 0)
                        <tr>
                            <td colspan="3" class="text-right text-muted">VAT</td>
                            <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->vat_amount) }}</td>
                        </tr>
                    @endif
                    @if ($vendorOrder->shipping_amount > 0)
                        <tr>
                            <td colspan="3" class="text-right text-muted">Shipping</td>
                            <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->shipping_amount) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td colspan="3" class="text-right">Vendor Total</td>
                        <td class="text-right">{{ \App\Support\Money::naira($vendorOrder->total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    <div class="clearfix">
        <div class="totals-box">
            <table>
                @if ($order->original_subtotal > $order->subtotal)
                    <tr>
                        <td>Original Price</td>
                        <td class="amount text-muted"><s>{{ \App\Support\Money::naira($order->original_subtotal) }}</s></td>
                    </tr>
                @endif
                <tr>
                    <td>Subtotal</td>
                    <td class="amount">{{ \App\Support\Money::naira($order->subtotal) }}</td>
                </tr>
                @if ($order->discount_total > 0)
                    <tr>
                        <td>Discount</td>
                        <td class="amount">&minus;{{ \App\Support\Money::naira($order->discount_total) }}</td>
                    </tr>
                @endif
                @if ($order->vat_total > 0)
                    <tr>
                        <td>VAT</td>
                        <td class="amount">{{ \App\Support\Money::naira($order->vat_total) }}</td>
                    </tr>
                @endif
                @if ($order->shipping_total > 0)
                    <tr>
                        <td>Shipping</td>
                        <td class="amount">{{ \App\Support\Money::naira($order->shipping_total) }}</td>
                    </tr>
                @endif
                <tr class="grand">
                    <td>Total Paid</td>
                    <td class="amount">{{ \App\Support\Money::naira($order->total) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="protection-note">
        <strong>Buyer Protection:</strong> This payment is held securely in escrow until you confirm receipt of every
        item, or your return window passes. Each vendor ships their own items independently — see the order tracking
        page for live status per vendor.
    </div>
@endsection
