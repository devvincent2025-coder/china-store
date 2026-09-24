@extends('emails.layout')

@section('content')
    <h1 class="heading" style="margin:0 0 6px;font-size:22px;color:#111827;">We couldn't process your payment</h1>
    <p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.5;">
        Hi {{ $order->user->name }}, your payment for order <strong>{{ $order->order_number }}</strong> did not go through.
        No money has left your account — your items are still reserved, so you can safely try again.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border-radius:8px;margin-bottom:24px;">
        <tr>
            <td style="padding:16px 20px;">
                <p style="margin:0;font-size:12px;color:#991b1b;">Order number</p>
                <p class="heading" style="margin:2px 0 0;font-size:16px;font-weight:700;color:#111827;">{{ $order->order_number }}</p>
            </td>
            <td style="padding:16px 20px;text-align:right;">
                <p style="margin:0;font-size:12px;color:#991b1b;">Amount</p>
                <p class="heading" style="margin:2px 0 0;font-size:16px;font-weight:700;color:#dc2626;">&#8358;{{ number_format($order->total / 100, 2) }}</p>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <a href="{{ url('/orders/'.$order->order_number) }}" class="button" style="background:#FF5000;color:#ffffff;">View Order &amp; Retry Payment</a>
            </td>
        </tr>
    </table>

    <p style="margin:24px 0 0;font-size:12px;color:#9ca3af;line-height:1.6;">
        Common reasons a card is declined: insufficient funds, an incorrect card PIN/OTP, or your bank blocking
        online payments. If this keeps happening, try a different card or contact your bank, or reach out to our
        support team and we'll help you sort it out.
    </p>
@endsection
