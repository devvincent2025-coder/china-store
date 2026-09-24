<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale priority: an explicit ?lang= query param (set by the language switcher) > the
 * authenticated user's saved preference > the session's last choice > the app default. Guests who
 * switch language get it remembered via session so it persists across pages without an account.
 */
class SetLocaleFromUser
{
    private const SUPPORTED = ['en', 'zh', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('lang');

        if ($locale && in_array($locale, self::SUPPORTED, true)) {
            session(['locale' => $locale]);

            if ($request->user()) {
                $request->user()->update(['locale' => $locale]);
            }
        } else {
            $locale = $request->user()?->locale ?? session('locale');
        }

        if ($locale && in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
