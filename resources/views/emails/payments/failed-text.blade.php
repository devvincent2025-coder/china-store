We couldn't process your payment — Order {{ $order->order_number }}

Hi {{ $order->user->name }},

Your payment of NGN {{ number_format($order->total / 100, 2) }} for order {{ $order->order_number }} did not go
through. No money has left your account, and your items are still reserved, so you can safely try again.

View your order and retry payment: {{ url('/orders/'.$order->order_number) }}

If this keeps happening, try a different card or contact your bank, or reach out to our support team.

— ChinaInNigeria
