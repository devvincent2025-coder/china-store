@extends('emails.layout')

@php
    $titleFor = fn ($item) => $item->product_title_snapshot['en'] ?? reset($item->product_title_snapshot) ?: 'Product';
@endphp

@section('content')
    <h1 class="heading" style="margin:0 0 6px;font-size:22px;color:#111827;">Payment received &#127881;</h1>
    <p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.5;">
        Hi {{ $order->user->name }}, thanks for your order — here's your receipt with everything you purchased.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border-radius:8px;margin-bottom:24px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0;font-size:12px;color:#6b7280;">Order number</p>
                <p class="heading" style="margin:2px 0 0;font-size:16px;font-weight:700;color:#111827;">{{ $order->order_number }}</p>
            </td>
            <td style="padding:16px 20px;text-align:right;">
                <p style="margin:0;font-size:12px;color:#6b7280;">Amount paid</p>
                <p class="heading" style="margin:2px 0 0;font-size:16px;font-weight:700;color:#16a34a;">&#8358;{{ number_format($order->total / 100, 2) }}</p>
            </td>
        </tr>
    </table>

    @foreach ($order->vendorOrders as $vendorOrder)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;border:1px solid #eee;border-radius:8px;overflow:hidden;">
            <tr>
                <td style="background:#fafafa;padding:10px 16px;font-size:13px;font-weight:700;color:#111827;border-bottom:1px solid #eee;" colspan="2">
                    {{ $vendorOrder->vendor->business_name }}
                </td>
            </tr>
            @foreach ($vendorOrder->items as $item)
                <tr>
                    <td style="padding:10px 16px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6;">
                        {{ $titleFor($item) }} &times; {{ $item->quantity }}
                    </td>
                    <td style="padding:10px 16px;font-size:13px;color:#111827;font-weight:600;text-align:right;border-bottom:1px solid #f3f4f6;">
                        &#8358;{{ number_format($item->line_total / 100, 2) }}
                    </td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" style="padding:10px 16px;font-size:12px;color:#6b7280;">
                    Subtotal &#8358;{{ number_format($vendorOrder->subtotal / 100, 2) }}
                    &middot; VAT &#8358;{{ number_format($vendorOrder->vat_amount / 100, 2) }}
                    &middot; Shipping &#8358;{{ number_format($vendorOrder->shipping_amount / 100, 2) }}
                    <strong style="float:right;color:#111827;">Total &#8358;{{ number_format($vendorOrder->total / 100, 2) }}</strong>
                </td>
            </tr>
        </table>
    @endforeach

    <p class="heading" style="margin:0 0 4px;font-size:13px;font-weight:700;color:#111827;">Shipping to</p>
    <p style="margin:0 0 28px;font-size:13px;color:#6b7280;line-height:1.6;">
        {{ $order->shipping_address['name'] ?? '' }}<br>
        {{ $order->shipping_address['line1'] ?? '' }}, {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }}<br>
        {{ $order->shipping_address['phone'] ?? '' }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <a href="{{ url('/orders/'.$order->order_number) }}" class="button" style="background:#FF5000;color:#ffffff;">View Order</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;font-size:12px;color:#9ca3af;line-height:1.6;">
        Each vendor ships their items separately, so you'll get an update as each part of your order moves.
        Your money stays protected in escrow until you confirm receipt.
    </p>
@endsection
