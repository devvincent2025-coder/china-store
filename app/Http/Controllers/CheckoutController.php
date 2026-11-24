<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use App\Services\Checkout\CheckoutService;
use App\Services\Coupon\CouponService;
use App\Services\Korapay\KorapayRequestException;
use App\Services\Korapay\KorapayService;
use App\Support\NigerianStates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    /**
     * Session key holding vendor_id => coupon code for codes the buyer has applied on this visit
     * to the checkout page. Staged server-side (not just held in the React form) so the amount
     * actually charged at store() can never diverge from what was previewed on this page — the
     * buyer never has to "trust" a client-computed discount, and can't tamper with it either.
     */
    private const SESSION_KEY = 'checkout.coupons';

    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CouponService $couponService,
        private readonly KorapayService $korapay,
    ) {
    }

    /**
     * Applies the vendor's own VAT/shipping configuration AND any coupon codes staged in the
     * session (see applyCoupon()) to build a live, fully accurate preview — the exact numbers
     * shown here are what CheckoutService::checkout() will charge, since both read the same
     * session-staged coupons and run them through the same CouponService validation.
     */
    public function index(Request $request): Response
    {
        $cart = $request->user()->cart;
        $stagedCoupons = $request->session()->get(self::SESSION_KEY, []);
        // Coupons that stopped qualifying since they were applied (e.g. the cart dropped below the
        // minimum spend) — surfaced to the buyer as a warning instead of vanishing silently.
        $couponNotices = [];

        $itemsByVendor = $cart?->itemsGroupedByVendor()->map(function ($items) use ($request, $stagedCoupons, &$couponNotices) {
            $vendor = $items->first()->vendor;
            $originalSubtotal = $items->sum(fn ($item) => $item->original_unit_price * $item->quantity);
            $subtotal = $items->sum(fn ($item) => $item->unit_price * $item->quantity);

            [$discount, $appliedCode, $notice] = $this->resolveStagedDiscount(
                $request,
                $vendor->id,
                $subtotal,
                $items->pluck('product_id')->all(),
                $stagedCoupons,
            );
            if ($notice) {
                $couponNotices[] = $notice;
            }

            $afterDiscount = $subtotal - $discount;
            $vatAmount = $vendor->calculateVat($afterDiscount);
            $shippingAmount = $vendor->calculateShippingFee($afterDiscount);

            return [
                'vendor' => $vendor->load('store'),
                'items' => $items,
                'original_subtotal' => $originalSubtotal,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'applied_coupon_code' => $appliedCode,
                'vat_amount' => $vatAmount,
                'shipping_amount' => $shippingAmount,
                'total' => $afterDiscount + $vatAmount + $shippingAmount,
            ];
        })->values() ?? [];

        return Inertia::render('Checkout/Index', [
            'itemsByVendor' => $itemsByVendor,
            'couponNotices' => $couponNotices,
            // The buyer's full address book (see ShippingAddress) — Checkout/Index.jsx lets them
            // pick any saved address or add a new one, rather than being limited to a single
            // overwritable "default" the way the old Profile-page address field worked.
            'savedAddresses' => $request->user()->shippingAddresses,
            'escrowReturnWindowDays' => (int) Setting::get('escrow_return_window_days', config('services.escrow.return_window_days')),
        ]);
    }

    /**
     * Validates a coupon code against the buyer's current cart for one vendor and, if valid,
     * stages it in the session so the checkout preview (index() above) and the actual charge
     * (store() -> CheckoutService) both reflect it identically.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $cart = $request->user()->cart;
        $items = $cart?->itemsGroupedByVendor()->get($validated['vendor_id']);

        if (! $items || $items->isEmpty()) {
            throw ValidationException::withMessages([
                "coupons.{$validated['vendor_id']}" => 'This store has no items in your cart.',
            ]);
        }

        $subtotal = $items->sum(fn ($item) => $item->unit_price * $item->quantity);

        try {
            $coupon = $this->couponService->validateForVendorGroup(
                code: $validated['code'],
                vendorId: $validated['vendor_id'],
                subtotalKobo: $subtotal,
                productIds: $items->pluck('product_id')->all(),
                user: $request->user(),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                "coupons.{$validated['vendor_id']}" => $e->validator->errors()->first('coupon'),
            ]);
        }

        $coupons = $request->session()->get(self::SESSION_KEY, []);
        // Stage the canonical code (not whatever casing the buyer typed).
        $coupons[$validated['vendor_id']] = $coupon->code;
        $request->session()->put(self::SESSION_KEY, $coupons);

        $saving = number_format($coupon->calculateDiscount($subtotal) / 100, 2);

        return back()->with('success', "Coupon {$coupon->code} applied — you save ₦{$saving}.");
    }

    public function removeCoupon(Request $request, int $vendorId): RedirectResponse
    {
        $coupons = $request->session()->get(self::SESSION_KEY, []);
        $removed = $coupons[$vendorId] ?? null;
        unset($coupons[$vendorId]);
        $request->session()->put(self::SESSION_KEY, $coupons);

        return back()->with('success', $removed ? "Coupon {$removed} removed." : 'Coupon removed.');
    }

    public function store(Request $request): SymfonyResponse
    {
        $usingSavedAddress = $request->filled('address_id');

        // Two mutually exclusive shapes depending on whether the buyer picked a saved address
        // (see ShippingAddress) or is entering a brand new one — `required_if` isn't used here
        // since Laravel skips that rule entirely when `address_id` is missing from the payload
        // rather than treating a missing key as "null", which would have let an empty new-address
        // form slip through unvalidated whenever the frontend simply omits the field.
        $rules = [
            'address_id' => ['nullable', 'integer'],
            'save_address' => ['boolean'],
        ];

        if (! $usingSavedAddress) {
            // Trim everything and strip spaces/dashes/brackets from the phone number first, so
            // "0803 123 4567" and "+234-803-123-4567" validate (and are stored) the same way.
            $address = collect((array) $request->input('shipping_address', []))
                ->map(fn ($value) => is_string($value) ? trim($value) : $value);
            if (is_string($address->get('phone'))) {
                $address->put('phone', preg_replace('/[\s\-()]/', '', $address->get('phone')));
            }
            $request->merge(['shipping_address' => $address->all()]);

            $rules += [
                'shipping_address.name' => ['required', 'string', 'min:2', 'max:100'],
                // Nigerian mobile: 0803…, 234803… or +234803… (11 digits locally).
                'shipping_address.phone' => ['required', 'string', 'regex:/^(\+?234|0)[789][01]\d{8}$/'],
                'shipping_address.line1' => ['required', 'string', 'min:5', 'max:255'],
                'shipping_address.city' => ['required', 'string', 'min:2', 'max:80'],
                'shipping_address.state' => ['required', 'string', Rule::in(NigerianStates::ALL)],
            ];
        }

        $validated = $request->validate($rules, [
            'shipping_address.name.required' => 'Full name is required.',
            'shipping_address.name.min' => 'Please enter your full name.',
            'shipping_address.phone.required' => 'Phone number is required.',
            'shipping_address.phone.regex' => 'Enter a valid Nigerian phone number, e.g. 08031234567.',
            'shipping_address.line1.required' => 'Address is required.',
            'shipping_address.line1.min' => 'Please enter your full street address.',
            'shipping_address.city.required' => 'City is required.',
            'shipping_address.city.min' => 'Please enter a valid city.',
            'shipping_address.state.required' => 'State is required.',
            'shipping_address.state.in' => 'Please choose a state from the list.',
        ]);

        if ($usingSavedAddress) {
            // Belongs-to-this-user check via the query itself (not just `find()`) — a buyer must
            // never be able to ship an order to another buyer's saved address by guessing its id.
            $saved = $request->user()->shippingAddresses()->findOrFail($validated['address_id']);
            $shippingAddress = $saved->only(['name', 'phone', 'line1', 'city', 'state']);
        } else {
            $shippingAddress = $validated['shipping_address'];

            if ($request->boolean('save_address')) {
                // A fresh address is always appended to the book, never overwrites an existing
                // one — this is exactly the "let them add more than one" behavior the single
                // default_shipping_address column couldn't support. The very first address a
                // buyer ever saves becomes their default so it's pre-selected next time.
                $request->user()->shippingAddresses()->create([
                    ...$shippingAddress,
                    'is_default' => ! $request->user()->shippingAddresses()->exists(),
                ]);
            }
        }

        // Sourced from the session, not the request body — this is exactly what was validated and
        // shown on the checkout page's preview, so the buyer can never be charged a different
        // discount than what they saw right before paying.
        $stagedCoupons = $request->session()->get(self::SESSION_KEY, []);

        try {
            $result = $this->checkoutService->checkout(
                user: $request->user(),
                cart: $request->user()->cart,
                shippingAddress: $shippingAddress,
                couponCodesByVendorId: $stagedCoupons,
                redirectUrl: route('checkout.callback'),
            );
        } catch (KorapayRequestException|ConnectionException $e) {
            // Payment couldn't be started (provider down, misconfigured keys, network). The order
            // was already rolled back and the cart restored (CheckoutService::abandonUnstartedOrder),
            // and staged coupons are kept — so the buyer can simply try again.
            report($e);

            return back()->with('error', __("We couldn't connect to our payment provider. Nothing was charged and your cart is unchanged — please try again in a moment."));
        }

        $request->session()->forget(self::SESSION_KEY);

        // Korapay's checkout page is a different origin, so a plain redirect()->away() would be
        // followed by Inertia's XHR client itself (and blocked by CORS) rather than navigating the
        // browser tab — the page would appear to "hang" with no visible error. Inertia::location()
        // replies with a 409 + X-Inertia-Location header, which the frontend recognizes as a hard
        // `window.location` navigation instead of an Inertia visit (same fix already used by
        // InvoicePaymentController/AdCampaignController for the exact same Korapay redirect).
        return Inertia::location($result['checkout_url'] ?? route('orders.show', $result['order']->order_number));
    }

    /**
     * Korapay redirects the buyer's browser here after checkout. Payment status itself is
     * confirmed via the webhook (see Http\Controllers\Webhooks\KorapayWebhookController) — this
     * route only needs to find the matching order and hand the buyer off to the order page.
     */
    /**
     * The webhook (KorapayWebhookController) is the primary payment-confirmation path, but it can
     * only ever reach a publicly reachable webhook URL — in local development (and anywhere
     * Korapay's servers can't reach) it never arrives. So this also actively verifies the charge as
     * a synchronous fallback via Korapay's API — see CheckoutService::confirmPaid(), which is
     * idempotent, so running both paths is safe.
     */
    public function callback(Request $request): RedirectResponse
    {
        $order = Order::where('payment_reference', $request->query('reference'))->firstOrFail();

        if ($order->payment_status !== 'paid') {
            try {
                $result = $this->korapay->verifyCharge($order->payment_reference);

                if (($result['status'] ?? null) === 'success') {
                    $this->checkoutService->confirmPaid($order);
                } elseif (in_array($result['status'] ?? null, ['failed', 'declined'], true)) {
                    $this->checkoutService->confirmFailed($order);
                }
            } catch (KorapayRequestException $e) {
                Log::warning('Order payment verification failed on callback', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // One-shot flag (see HandleInertiaRequests's `flash.paymentResult`) so Orders/Show.jsx pops
        // the full-screen success/failure modal right after the buyer lands back from Korapay —
        // read fresh from the order rather than trusting whatever verifyCharge() just returned,
        // since the webhook may have already confirmed/failed it moments earlier.
        $request->session()->flash('paymentResult', [
            'status' => $order->fresh()->payment_status === 'paid' ? 'success' : 'failed',
            'order_number' => $order->order_number,
        ]);

        return redirect()->route('orders.show', $order->order_number);
    }

    /**
     * Re-validates a staged coupon on every page load (not just when it was first applied) —
     * self-healing if the cart changed since (an item was removed, stock ran out, etc.) rather than
     * silently showing a stale discount. An invalid coupon is dropped from the session quietly; the
     * buyer just sees no discount rather than an error on a page they didn't just submit.
     *
     * @return array{0: int, 1: ?string} [discountKobo, appliedCode]
     */
    /**
     * @return array{0: int, 1: ?string, 2: ?string} [discount kobo, applied code, notice when a
     *                                                 staged coupon had to be dropped]
     */
    private function resolveStagedDiscount(Request $request, int $vendorId, int $subtotalKobo, array $productIds, array $stagedCoupons): array
    {
        $code = $stagedCoupons[$vendorId] ?? null;

        if (! $code) {
            return [0, null, null];
        }

        try {
            $coupon = $this->couponService->validateForVendorGroup(
                code: $code,
                vendorId: $vendorId,
                subtotalKobo: $subtotalKobo,
                productIds: $productIds,
                user: $request->user(),
            );

            return [$coupon->calculateDiscount($subtotalKobo), $code, null];
        } catch (ValidationException $e) {
            $coupons = $request->session()->get(self::SESSION_KEY, []);
            unset($coupons[$vendorId]);
            $request->session()->put(self::SESSION_KEY, $coupons);

            return [0, null, "Coupon {$code} was removed: ".$e->validator->errors()->first('coupon')];
        }
    }
}
