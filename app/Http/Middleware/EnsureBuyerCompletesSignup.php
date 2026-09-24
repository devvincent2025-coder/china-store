<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confines a buyer who hasn't finished phone verification (see User::needsPhoneVerification()) to
 * the home page, where CompleteProfileModal lives (resources/js/Pages/Home.jsx) — mirrors
 * EnsureVendorIsActive's pattern of redirecting somewhere the required step can actually be
 * completed, rather than just blocking with a 403. Never true for a vendor or an account that
 * existed before the new OTP signup flow shipped (requires_phone_verification defaults to false).
 */
class EnsureBuyerCompletesSignup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->needsPhoneVerification()) {
            return redirect()->route('home')
                ->with('warning', 'Please verify your phone number to finish setting up your account.');
        }

        return $next($request);
    }
}
