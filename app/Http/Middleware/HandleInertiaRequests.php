<?php

namespace App\Http\Middleware;

use App\Services\Cart\CartResolver;
use App\Support\Seo\Seo;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cartResolver = app(CartResolver::class);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Lets the storefront header show a persistent "Vendor Dashboard" link for vendors
                // browsing as themselves — without this, a vendor who navigates back to the
                // storefront (or lands there after e.g. a stale bookmark) has no way back to their
                // own dashboard from the buyer-facing chrome.
                'isVendor' => $request->user()?->isVendor() ?? false,
                // Lets VendorLayout's "Back to storefront" link go straight to the vendor's own
                // public shop page instead of the generic homepage — null until the vendor has
                // completed onboarding far enough to have a VendorStore record.
                'vendorStoreSlug' => $request->user()?->vendor?->store?->slug,
                // Drives CompleteProfileModal on Pages/Home.jsx — see User::needsPhoneVerification()
                // and EnsureBuyerCompletesSignup, which keeps a buyer on the home page until this
                // clears.
                'needsPhoneVerification' => $request->user()?->needsPhoneVerification() ?? false,
            ],
            // True only when BOTH the admin guard and the web guard have a session at once — that
            // combination only ever happens mid-impersonation (see
            // Http\Controllers\Admin\ImpersonationController), since a normal buyer/vendor never
            // authenticates on the 'admin' guard. Drives the "Return to admin" banner shared across
            // every storefront/vendor layout.
            'impersonating' => auth('admin')->check() && auth('web')->check(),
            // Exposes the current locale plus its short-key JSON translations (lang/{locale}.json)
            // so the React/Inertia frontend can call these directly once it wires up its own
            // i18n helper — this only shares the data, it does not translate anything itself.
            // Lang::get('*') does not return the whole JSON file, so we read it directly.
            'locale' => app()->getLocale(),
            'translations' => $this->loadJsonTranslations(app()->getLocale()),
            // Generic session-flash bridge — controllers already call back()->with('success', ...)
            // pervasively; this is what actually surfaces those (and one-shot values like
            // justRegistered/recovery_codes) as Inertia props instead of them being silently dropped.
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'warning' => session('warning'),
                'justRegistered' => session('justRegistered', false),
                'justRegisteredVendor' => session('justRegisteredVendor', false),
                // One-shot flag set by CheckoutController::callback() so Orders/Show.jsx knows to
                // pop the full-screen payment success/failure modal right after the buyer is
                // bounced back from Korapay — never persisted beyond this single page load.
                'paymentResult' => session('paymentResult'),
            ],
            // Sum of line quantities so the header cart badge stays accurate across pages — a
            // guest's own (cookie-identified) cart counts too, see CartResolver::count().
            'cartCount' => $cartResolver->count($request),
            // Fallback SEO data every page gets even before it's been given bespoke treatment via
            // a page-specific `seo` prop (see App\Support\Seo\Seo) — read by both
            // resources/views/app.blade.php (server-side, first paint/crawlers) and
            // Components/SeoHead.jsx (client-side, SPA navigations). The site-wide Organization/
            // WebSite JSON-LD lives here rather than being repeated by every controller.
            'seoDefaults' => [
                'siteName' => Seo::SITE_NAME,
                'title' => Seo::SITE_NAME.' - Shop Chinese-Run Stores in Nigeria',
                'description' => 'Shop quality products from verified Chinese-run vendors across Nigeria. Naira pricing, fast local delivery, and every order protected by escrow.',
                'image' => Seo::absoluteUrl(Seo::DEFAULT_IMAGE),
                'canonical' => Seo::absoluteUrl($request->getRequestUri()),
                'robots' => 'index,follow',
                'jsonLd' => Seo::organizationJsonLd(),
            ],
        ];
    }

    /**
     * Reads lang/{locale}.json directly, since Laravel's translator only exposes JSON
     * translations key-by-key (there is no built-in "get the whole file" helper).
     */
    protected function loadJsonTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
