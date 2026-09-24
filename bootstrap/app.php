<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Locale must run before HandleInertiaRequests so shared `locale` / `translations`
        // props reflect ?lang= (and session/user preference) on the same request.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsNotSuspended::class,
            \App\Http\Middleware\SetLocaleFromUser::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'vendor.active' => \App\Http\Middleware\EnsureVendorIsActive::class,
            'buyer.signup-complete' => \App\Http\Middleware\EnsureBuyerCompletesSignup::class,
        ]);

        // Korapay's webhook is an external server-to-server POST with no Laravel session, so it
        // can never carry a CSRF token — authenticity is verified via HMAC signature instead (see
        // Http\Controllers\Webhooks\KorapayWebhookController::hasValidSignature()).
        $middleware->validateCsrfTokens(except: [
            'webhooks/korapay',
            // navigator.sendBeacon (used to report ad dwell-time on tab close/navigation-away —
            // see Pages/Vendors/Show.jsx's useAdDwellTracking()) cannot attach custom headers, so
            // it can never carry a CSRF token. Low-stakes endpoint (just increments a counter),
            // same trust model as the webhook above.
            'go/*/ping',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Renders a branded storefront page instead of Laravel's bare default error pages for
        // routine client-facing statuses (a stale product link, a locked-out admin route, an
        // expired form token). 500/503 stay on Laravel's normal debug output while APP_DEBUG=true,
        // since a real bug during development is more useful as a full stack trace than a pretty page.
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with(['message' => 'The page expired, please try again.']);
            }

            $isRoutineClientError = in_array($status, [404, 403], true);
            $isProductionServerError = ! config('app.debug') && in_array($status, [500, 503], true);

            if ($isRoutineClientError || $isProductionServerError) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
