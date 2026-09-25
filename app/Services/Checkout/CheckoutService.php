<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmed;
use App\Notifications\PaymentFailed;
use App\Notifications\VendorNewOrderReceived;
use App\Services\Commission\CommissionService;
use App\Services\Coupon\CouponService;
use App\Services\Korapay\KorapayService;
use App\Services\Ledger\LedgerService;
use App\Services\Pricing\TieredPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a multi-vendor cart into ONE order containing multiple vendor_order groups, then charges
 * the buyer's single payment straight to the platform's own Korapay balance — no split_payment at
 * charge time. Each vendor_order still records its own commission_amount/payout_amount, but that
 * money is held in escrow (vendor_orders.escrow_status starts 'holding') and only disbursed to the
 * vendor later, once delivery is confirmed and the return window has passed with no open dispute
 * — see App\Services\Escrow\EscrowService.
 */
class CheckoutService
{
    public function __construct(
        private readonly CouponService $couponService,
        private readonly KorapayService $korapay,
        private readonly LedgerService $ledger,
    ) {
    }

    /**
     * Marks an order paid and fires every "you got paid"/"your order is confirmed" side effect —
     * shared by KorapayWebhookController (the primary, production path) and CheckoutController::
     * callback() (a synchronous fallback for when Korapay's webhook can never reach this app, e.g.
     * local development with no public tunnel). Idempotent: safe to call from both without double
     * -notifying, since it no-ops once payment_status is already 'paid'.
     */
    public function confirmPaid(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);

        $order->user->notify(new OrderConfirmed($order));

        foreach ($order->vendorOrders as $vendorOrder) {
            $this->ledger->recordSale($vendorOrder);
            $vendorOrder->vendor->user->notify(new VendorNewOrderReceived($vendorOrder));
        }
    }

    /**
     * Marks an order's payment as failed/declined and notifies the buyer — shared by
     * KorapayWebhookController's `charge.failed` branch and CheckoutController::callback()'s
     * synchronous fallback, the same way confirmPaid() is shared by both success paths. Idempotent:
     * a charge already confirmed paid (e.g. the webhook beat a stale callback here) is never
     * downgraded, and a charge already marked failed is never re-notified.
     */
    public function confirmFailed(Order $order): void
    {
        if (in_array($order->payment_status, ['paid', 'failed'], true)) {
            return;
        }

        $order->update(['payment_status' => 'failed']);

        $order->user->notify(new PaymentFailed($order));
    }

    /**
     * @param  array<int, string>  $couponCodesByVendorId  vendor_id => coupon code, one coupon per vendor group
     */
    public function checkout(User $user, Cart $cart, array $shippingAddress, array $couponCodesByVendorId, string $redirectUrl): array
    {
        $itemsByVendor = $cart->itemsGroupedByVendor();

        if ($itemsByVendor->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = DB::transaction(function () use ($user, $cart, $itemsByVendor, $shippingAddress, $couponCodesByVendorId) {
            $this->assertStockAvailable($itemsByVendor);
            $this->revalidateTieredPricing($itemsByVendor);

            $orderOriginalSubtotal = 0;
            $orderSubtotal = 0;
            $orderDiscount = 0;
            $orderVat = 0;
            $orderShipping = 0;
            $vendorOrderPayloads = [];

            foreach ($itemsByVendor as $vendorId => $items) {
                $originalSubtotal = $items->sum(fn ($item) => $item->original_unit_price * $item->quantity);
                $subtotal = $items->sum(fn ($item) => $item->unit_price * $item->quantity);
                $discount = 0;
                $coupon = null;

                if ($code = $couponCodesByVendorId[$vendorId] ?? null) {
                    $coupon = $this->couponService->validateForVendorGroup(
                        code: $code,
                        vendorId: $vendorId,
                        subtotalKobo: $subtotal,
                        productIds: $items->pluck('product_id')->all(),
                        user: $user,
                    );
                    $discount = $coupon->calculateDiscount($subtotal);
                }

                $vendor = $items->first()->vendor;
                $afterDiscount = $subtotal - $discount;

                // VAT is a single platform-wide rate (App\Models\Setting::vatRate(), see
                // Vendor::calculateVat()) — every vendor charges the same rate. Shipping is still
                // the vendor's own configuration (Vendor::calculateShippingFee()). Both are paid
                // out to the vendor in full — the platform's commission below is charged only on
                // goods revenue, never on tax or shipping.
                $vatAmount = $vendor->calculateVat($afterDiscount);
                $shippingAmount = $vendor->calculateShippingFee($afterDiscount);
                $total = $afterDiscount + $vatAmount + $shippingAmount;

                // Tiered by THIS order's own goods revenue (afterDiscount) — see
                // CommissionService's doc comment for how this mirrors Shopify's own
                // volume-based fee tiers. Falls back to the vendor's flat commission_rate when no
                // tier matches, so vendors without any configured tiers behave exactly as before.
                [$commissionRate] = CommissionService::rateFor($vendor, $afterDiscount);
                $commission = $this->applyPercentage($afterDiscount, $commissionRate);

                $orderOriginalSubtotal += $originalSubtotal;
                $orderSubtotal += $subtotal;
                $orderDiscount += $discount;
                $orderVat += $vatAmount;
                $orderShipping += $shippingAmount;

                $vendorOrderPayloads[] = [
                    'vendor' => $vendor,
                    'items' => $items,
                    'coupon' => $coupon,
                    'original_subtotal' => $originalSubtotal,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'vat_amount' => $vatAmount,
                    'shipping_amount' => $shippingAmount,
                    'total' => $total,
                    'commission_amount' => $commission,
                    'commission_rate' => $commissionRate,
                    'payout_amount' => $total - $commission,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'currency' => 'NGN',
                'original_subtotal' => $orderOriginalSubtotal,
                'subtotal' => $orderSubtotal,
                'discount_total' => $orderDiscount,
                'vat_total' => $orderVat,
                'shipping_total' => $orderShipping,
                'total' => ($orderSubtotal - $orderDiscount) + $orderVat + $orderShipping,
                'payment_reference' => (string) Str::uuid(),
                'payment_status' => 'pending',
                'shipping_address' => $shippingAddress,
            ]);

            foreach ($vendorOrderPayloads as $payload) {
                $vendorOrder = $order->vendorOrders()->create([
                    'vendor_id' => $payload['vendor']->id,
                    'coupon_id' => $payload['coupon']?->id,
                    'original_subtotal' => $payload['original_subtotal'],
                    'subtotal' => $payload['subtotal'],
                    'discount' => $payload['discount'],
                    'vat_amount' => $payload['vat_amount'],
                    'shipping_amount' => $payload['shipping_amount'],
                    'total' => $payload['total'],
                    'commission_amount' => $payload['commission_amount'],
                    'commission_rate' => $payload['commission_rate'],
                    'payout_amount' => $payload['payout_amount'],
                    'fulfillment_status' => 'processing',
                ]);

                $vendorOrder->statusHistory()->create(['status' => 'processing']);

                foreach ($payload['items'] as $item) {
                    $vendorOrder->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        // $item->product->title (the magic accessor) resolves to a single plain
                        // string in whatever the CURRENT request locale happens to be — getTranslations()
                        // is required to snapshot the full {en,zh,fr} object multi-locale order
                        // history actually needs.
                        'product_title_snapshot' => $item->product->getTranslations('title'),
                        'product_image_snapshot' => $item->variant?->display_image ?? $item->product->images->first()?->display_url,
                        // Snapshotted so the order history keeps showing e.g. "Carton of 24 / Red"
                        // even if the vendor later edits or deletes the variant/option itself.
                        'variant_label_snapshot' => $item->variant?->label_snapshot,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->unit_price * $item->quantity,
                    ]);

                    $this->deductStock($item);
                }

                if ($payload['coupon']) {
                    $this->couponService->recordUsage($payload['coupon'], $user, $vendorOrder->id, $payload['discount']);
                }
            }

            $cart->items()->delete();

            return $order;
        });

        // Deliberately outside the transaction: this is a network call, and the buyer's order rows
        // (with stock already deducted) should not sit behind open row locks while we wait on it.
        // The buyer is redirected synchronously to Korapay's hosted page, so this one call can't be
        // queued like the rest of the Korapay integration — it blocks the response by necessity.
        $charge = $this->korapay->initializeCharge(
            reference: $order->payment_reference,
            amountNaira: $order->total / 100,
            customerEmail: $user->email,
            customerName: $user->name,
            redirectUrl: $redirectUrl,
        );

        return [
            'order' => $order,
            'checkout_url' => $charge['checkout_url'] ?? null,
        ];
    }

    private function assertStockAvailable($itemsByVendor): void
    {
        foreach ($itemsByVendor as $items) {
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                $available = $item->product_variant_id
                    ? $product->variants()->lockForUpdate()->find($item->product_variant_id)?->stock ?? 0
                    : $product->stock;

                // Re-read under lock at the moment of ordering — another buyer may have bought the
                // stock since this cart was filled, even though CartController capped it back then.
                if ($available < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => $available <= 0
                            ? __('Sorry, ":title" has just sold out. Please remove it from your cart to continue.', ['title' => $product->title])
                            : __('Only :available of ":title" left in stock, but your cart has :quantity. Please reduce the quantity to continue.', [
                                'available' => $available,
                                'title' => $product->title,
                                'quantity' => $item->quantity,
                            ]),
                    ]);
                }
            }
        }
    }

    /**
     * A cart line's unit_price is a snapshot from whenever it was last added/updated (see
     * CartController) — this is the final gate before that price becomes a permanent order_items
     * row, so it re-derives each line's price fresh from the product/variant's CURRENT tiers
     * (updating $item->unit_price in-memory, which the sum()s just below this call then use) and
     * rejects the whole checkout if a vendor has since raised the minimum order quantity above
     * what the buyer actually has in their cart. Without this, a tier price agreed to at
     * add-to-cart time could otherwise drift from what the vendor's tiers say right now.
     */
    private function revalidateTieredPricing($itemsByVendor): void
    {
        foreach ($itemsByVendor as $items) {
            foreach ($items as $item) {
                $moq = TieredPricingService::minOrderQuantity($item->product, $item->variant);

                if ($item->quantity < $moq) {
                    throw ValidationException::withMessages([
                        'cart' => "The quantity for {$item->product->getTranslation('title', 'en')} is below its current minimum order quantity ({$moq}) — please update your cart.",
                    ]);
                }

                $currentPrice = TieredPricingService::priceForQuantity($item->product, $item->variant, $item->quantity);

                if ($currentPrice !== null) {
                    $item->unit_price = $currentPrice;
                }
            }
        }
    }

    private function deductStock($item): void
    {
        if ($item->product_variant_id) {
            $item->variant()->decrement('stock', $item->quantity);
        } else {
            $item->product()->decrement('stock', $item->quantity);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'CIN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    /**
     * Integer basis-point math so a rate like 7.50% commission never truncates to 7% the way
     * `(int) $rate` would — mirrors Vendor::calculateVat()/calculateShippingFee()'s own math.
     */
    private function applyPercentage(int $amountKobo, float $percentage): int
    {
        $basisPoints = (int) round($percentage * 100);

        return intdiv($amountKobo * $basisPoints, 10000);
    }
}
