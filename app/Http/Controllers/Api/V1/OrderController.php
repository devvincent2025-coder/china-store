<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return response()->json([
            'data' => $orders->getCollection()->map($this->summarize(...))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        // Bound by order_number in the route, but ownership still has to be checked explicitly —
        // Laravel's route-model binding has no notion of "belongs to this token's user".
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load('vendorOrders.items');

        return response()->json(['data' => [
            ...$this->summarize($order),
            'shipping_address' => $order->shipping_address,
            'vendor_orders' => $order->vendorOrders->map(fn ($vendorOrder) => [
                'vendor_id' => $vendorOrder->vendor_id,
                'fulfillment_status' => $vendorOrder->fulfillment_status,
                'total_naira' => $vendorOrder->total / 100,
                'items' => $vendorOrder->items->map(fn ($item) => [
                    'title' => $item->product_title_snapshot,
                    'variant_label' => $item->variant_label_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price_naira' => $item->unit_price / 100,
                    'line_total_naira' => $item->line_total / 100,
                ]),
            ]),
        ]]);
    }

    private function summarize(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'total_naira' => $order->total / 100,
            'currency' => $order->currency,
            'payment_status' => $order->payment_status,
            'placed_at' => $order->created_at->toIso8601String(),
        ];
    }
}
