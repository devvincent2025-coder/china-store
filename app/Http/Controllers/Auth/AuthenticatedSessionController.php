<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartResolver;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly CartResolver $carts)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Supports logging in with either an email or a phone number (the buyer picks a tab on the
     * frontend) — no OTP step here. If the resolved account has TOTP two-factor authentication
     * confirmed (see User::hasEnabledTwoFactorAuthentication()), credentials alone are not enough:
     * we defer Auth::login() and hand off to TwoFactorChallengeController instead.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_method' => ['required', 'in:email,phone'],
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($validated['identifier']).'|'.$request->ip());
        $this->ensureIsNotRateLimited($request, $throttleKey);

        $user = $validated['login_method'] === 'phone'
            ? $this->findUserByPhone($validated['identifier'])
            : User::where('email', $validated['identifier'])->first();

        $loginField = $validated['login_method'] === 'phone' ? 'phone' : 'email';

        if (! $user || ! Auth::validate([
            $loginField => $user->{$loginField},
            'password' => $validated['password'],
        ])) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'identifier' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->is_suspended) {
            throw ValidationException::withMessages([
                'identifier' => 'This account has been suspended. Contact support if you believe this is a mistake.',
            ]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $validated['remember'] ?? false);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $validated['remember'] ?? false);
        $request->session()->regenerate();
        $this->carts->mergeGuestCartIntoUser($request, $user);

        return redirect()->intended($user->postLoginRoute());
    }

    /**
     * Match phone logins across common formats (+234801…, 234801…, 0801…).
     */
    private function findUserByPhone(string $identifier): ?User
    {
        $digits = preg_replace('/\D+/', '', $identifier) ?? '';

        if ($digits === '') {
            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $identifier,
            '+'.$digits,
            $digits,
            // Nigeria local: +234801… ↔ 0801…
            str_starts_with($digits, '234') && strlen($digits) > 3 ? '0'.substr($digits, 3) : null,
            str_starts_with($digits, '0') ? '234'.substr($digits, 1) : null,
            str_starts_with($digits, '0') ? '+234'.substr($digits, 1) : null,
        ])));

        return User::whereIn('phone', $candidates)->first();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // A logged-in buyer wanting to become a vendor has to do it on a separate account (vendor
        // registration is its own flow — see Auth\VendorRegisteredUserController — and is
        // guest-only), so OpenShop/Index.jsx's "log out and create a vendor account" button routes
        // through here rather than needing a general-purpose (and open-redirect-prone) "next URL"
        // param.
        if ($request->boolean('to_vendor_register')) {
            return redirect()->route('vendor.register');
        }

        return redirect('/');
    }

    private function ensureIsNotRateLimited(Request $request, string $throttleKey): void
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
