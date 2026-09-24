<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catches an account suspended AFTER the user already had an active session — the login-time
 * check in Auth\AuthenticatedSessionController only stops a NEW login, so a currently logged-in
 * user who gets suspended mid-session would otherwise keep working until their session naturally
 * expired. Applied to the whole 'web' middleware group (see bootstrap/app.php) so it covers both
 * the storefront/vendor Inertia routes and any authenticated action.
 */
class EnsureUserIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_suspended) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Your account has been suspended.');
        }

        return $next($request);
    }
}
