Payment received — Order {{ $order->order_number }}

Hi {{ $order->user->name }},

Thanks for your order! Amount paid: NGN {{ number_format($order->total / 100, 2) }}

@foreach ($order->vendorOrders as $vendorOrder)
{{ $vendorOrder->vendor->business_name }} — NGN {{ number_format($vendorOrder->total / 100, 2) }}
@foreach ($vendorOrder->items as $item)
  - {{ $item->product_title_snapshot['en'] ?? reset($item->product_title_snapshot) ?: 'Product' }} x{{ $item->quantity }} — NGN {{ number_format($item->line_total / 100, 2) }}
@endforeach
@endforeach

Shipping to:
{{ $order->shipping_address['name'] ?? '' }}
{{ $order->shipping_address['line1'] ?? '' }}, {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }}
{{ $order->shipping_address['phone'] ?? '' }}

View your order: {{ url('/orders/'.$order->order_number) }}

Each vendor ships their items separately, so you'll get an update as each part of your order moves.

— ChinaInNigeria
