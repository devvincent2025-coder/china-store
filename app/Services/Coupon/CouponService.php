<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Fully automated coupon validation — no admin involvement per the product spec. Coupons apply
 * per-vendor-group at checkout, so callers pass the subtotal and product ids for ONE vendor's
 * portion of the cart, not the whole order.
 */
class CouponService
{
    public function validateForVendorGroup(string $code, ?int $vendorId, int $subtotalKobo, array $productIds, User $user): Coupon
    {
        // Case/whitespace-insensitive: "newarrival15 " finds NEWARRIVAL15.
        $coupon = Coupon::query()->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => 'This coupon code does not exist.']);
        }

        if (! $coupon->isCurrentlyValid()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon has expired or is no longer active.']);
        }

        // A vendor-scoped coupon only applies to that vendor's items; a platform-wide coupon
        // (vendor_id null) applies everywhere.
        if ($coupon->vendor_id !== null && $coupon->vendor_id !== $vendorId) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is not valid for this store.']);
        }

        // If the coupon is scoped to specific products, every product in this vendor's cart group
        // that the coupon is meant to discount must be present — otherwise there's nothing to apply it to.
        $scopedProductIds = $coupon->products()->pluck('products.id');
        if ($scopedProductIds->isNotEmpty() && $scopedProductIds->intersect($productIds)->isEmpty()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon does not apply to any items in your cart.']);
        }

        if ($subtotalKobo < $coupon->minimum_spend) {
            $minimumNaira = number_format($coupon->minimum_spend / 100, 2);
            throw ValidationException::withMessages(['coupon' => "This coupon requires a minimum spend of ₦{$minimumNaira}."]);
        }

        return $coupon;
    }

    public function recordUsage(Coupon $coupon, User $user, int $vendorOrderId, int $discountAmountKobo): void
    {
        $coupon->usages()->create([
            'user_id' => $user->id,
            'vendor_order_id' => $vendorOrderId,
            'discount_amount' => $discountAmountKobo,
        ]);

        $coupon->increment('used_count');
    }
}
