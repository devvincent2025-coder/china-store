<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Turns the buyer's cart into an order via the same CheckoutService the storefront uses — no
 * coupon support here (an agent workflow has no session to stage one in the way
 * Http\Controllers\CheckoutController does), and deliberately no "charge immediately" path: the
 * response only ever hands back a checkout_url. Korapay's hosted payment page is where the actual
 * card/bank charge happens, so an AI agent (or a leaked token) can create an order on a buyer's
 * behalf but can never move money without the buyer themselves opening that link and paying.
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkoutService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $usingSavedAddress = $request->filled('address_id');

        $rules = ['address_id' => ['nullable', 'integer']];

        if (! $usingSavedAddress) {
            $rules += [
                'shipping_address.name' => ['required', 'string'],
                'shipping_address.phone' => ['required', 'string'],
                'shipping_address.line1' => ['required', 'string'],
                'shipping_address.city' => ['required', 'string'],
                'shipping_address.state' => ['required', 'string'],
            ];
        }

        $validated = $request->validate($rules);

        if ($usingSavedAddress) {
            $saved = $request->user()->shippingAddresses()->findOrFail($validated['address_id']);
            $shippingAddress = $saved->only(['name', 'phone', 'line1', 'city', 'state']);
        } else {
            $shippingAddress = $validated['shipping_address'];
        }

        if (! $request->user()->cart || $request->user()->cart->items()->doesntExist()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $result = $this->checkoutService->checkout(
            user: $request->user(),
            cart: $request->user()->cart,
            shippingAddress: $shippingAddress,
            couponCodesByVendorId: [],
            redirectUrl: route('checkout.callback'),
        );

        return response()->json([
            'data' => [
                'order_number' => $result['order']->order_number,
                'total_naira' => $result['order']->total / 100,
                'currency' => $result['order']->currency,
                'payment_status' => $result['order']->payment_status,
                // The buyer opens this in a browser to actually pay — never call it as an API
                // request; it's Korapay's own hosted checkout page.
                'checkout_url' => $result['checkout_url'],
            ],
        ], 201);
    }
}
