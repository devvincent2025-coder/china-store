<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Finds (or creates) "the current cart" for whoever's making the request — a logged-in buyer's own
 * Cart row as before, or, for a guest, one keyed by a random token held in an httpOnly cookie
 * rather than a user_id. This is what lets a buyer add their first item before ever logging in;
 * only checkout itself still requires an account (see routes/web.php — cart routes moved out of
 * the `auth` middleware group, checkout routes did not).
 */
class CartResolver
{
    private const COOKIE = 'cart_token';

    private const COOKIE_MINUTES = 60 * 24 * 30; // 30 days — long enough that clearing a session doesn't lose the cart

    public function resolve(Request $request): Cart
    {
        if ($request->user()) {
            return $request->user()->cart()->firstOrCreate();
        }

        $token = $request->cookie(self::COOKIE);

        if ($token) {
            $cart = Cart::where('guest_token', $token)->first();

            if ($cart) {
                return $cart;
            }
        }

        return $this->createGuestCart();
    }

    /**
     * The header cart badge's line-quantity count — deliberately does NOT call resolve() (which
     * creates a cart + hands out a fresh cookie on first touch), since that would mean every
     * anonymous page view that never adds anything still creates a cart row and a cookie. This
     * only ever reads what's already there.
     */
    public function count(Request $request): int
    {
        return (int) ($this->existing($request)?->items()->sum('quantity') ?? 0);
    }

    /**
     * The current buyer's cart if they already have one, without creating one — same reasoning as
     * count() above. Used anywhere a page only needs to READ the cart (e.g. the product page's
     * "already in your cart" state).
     */
    public function existing(Request $request): ?Cart
    {
        if ($request->user()) {
            return $request->user()->cart()->first();
        }

        $token = $request->cookie(self::COOKIE);

        return $token ? Cart::where('guest_token', $token)->first() : null;
    }

    private function createGuestCart(): Cart
    {
        $token = Str::random(48);

        Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);

        return Cart::create(['guest_token' => $token]);
    }

    /**
     * Called right after Auth::login() everywhere it happens (AuthenticatedSessionController,
     * TwoFactorChallengeController, the buyer OTP signup flow, vendor registration) — folds
     * whatever a buyer added to their guest cart into their now-authenticated one, merging
     * quantities on any line that already exists there rather than creating a duplicate.
     */
    public function mergeGuestCartIntoUser(Request $request, User $user): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! $token) {
            return;
        }

        $guestCart = Cart::where('guest_token', $token)->first();

        if (! $guestCart) {
            Cookie::queue(Cookie::forget(self::COOKIE));

            return;
        }

        $userCart = $user->cart ?? $user->cart()->create();

        foreach ($guestCart->items as $guestItem) {
            $existing = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            if ($existing) {
                // The existing line's unit_price wins (whichever was added to the account cart
                // first) — checkout re-validates price/stock/MOQ against the live product anyway,
                // so this is only ever a display concern in the meantime.
                $existing->update(['quantity' => $existing->quantity + $guestItem->quantity]);
            } else {
                $guestItem->update(['cart_id' => $userCart->id]);
            }
        }

        // Cascade-deletes whatever guest items were merged into an existing line above (their
        // cart_id was never changed, so they're still this cart's children) — the ones moved over
        // via the else branch already belong to $userCart now and are unaffected.
        $guestCart->delete();

        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}
