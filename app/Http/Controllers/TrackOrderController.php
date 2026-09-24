<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A public order-tracking flow for anyone with an order number — no login required. Verification
 * is order_number + the email on the order's account (there's no separate guest-checkout concept
 * in this app, every Order belongs to a User), and a successful check is remembered in the session
 * so re-visiting the tracking link doesn't ask again for the rest of the browser session.
 */
class TrackOrderController extends Controller
{
    private const SESSION_KEY = 'track_order.verified';

    public function index(Request $request): Response
    {
        return Inertia::render('TrackOrder/Index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->whereHas('user', fn ($query) => $query->where('email', $validated['email']))
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_number' => 'We couldn\'t find an order matching that order number and email.',
            ]);
        }

        $verified = $request->session()->get(self::SESSION_KEY, []);
        $verified[] = $order->order_number;
        $request->session()->put(self::SESSION_KEY, array_unique($verified));

        return redirect()->route('track-order.show', $order->order_number);
    }

    public function show(Request $request, string $orderNumber): Response|RedirectResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $isOwner = $request->user()?->can('view', $order) ?? false;
        $isVerifiedInSession = in_array($orderNumber, $request->session()->get(self::SESSION_KEY, []), true);

        if (! $isOwner && ! $isVerifiedInSession) {
            return redirect()->route('track-order.index')
                ->with('error', 'Please enter the order number and email to view tracking for that order.');
        }

        $order->load(['vendorOrders.vendor.store', 'vendorOrders.items', 'vendorOrders.statusHistory']);

        return Inertia::render('TrackOrder/Show', [
            // A curated, public-safe shape — never the raw models — so a vendor's commission/payout
            // figures (present on VendorOrder) can never leak onto a page anyone with the order
            // number and email can reach.
            'order' => [
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
                'created_at' => $order->created_at,
                'total' => $order->total,
                'shipping_address' => $order->shipping_address,
                'vendor_orders' => $order->vendorOrders->map(fn ($vendorOrder) => [
                    'id' => $vendorOrder->id,
                    'created_at' => $vendorOrder->created_at,
                    'fulfillment_status' => $vendorOrder->fulfillment_status,
                    'escrow_status' => $vendorOrder->escrow_status,
                    'tracking_number' => $vendorOrder->tracking_number,
                    'tracking_carrier' => $vendorOrder->tracking_carrier,
                    'subtotal' => $vendorOrder->subtotal,
                    'discount' => $vendorOrder->discount,
                    'vat_amount' => $vendorOrder->vat_amount,
                    'shipping_amount' => $vendorOrder->shipping_amount,
                    'total' => $vendorOrder->total,
                    'status_history' => $vendorOrder->statusHistory,
                    'vendor' => [
                        'business_name' => $vendorOrder->vendor->business_name,
                        'store' => $vendorOrder->vendor->store ? ['name' => $vendorOrder->vendor->store->name, 'slug' => $vendorOrder->vendor->store->slug] : null,
                    ],
                    'items' => $vendorOrder->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_title_snapshot' => $item->product_title_snapshot,
                        'product_image_snapshot' => $item->product_image_snapshot,
                        'variant_label_snapshot' => $item->variant_label_snapshot,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ]),
                ]),
            ],
        ]);
    }
}
