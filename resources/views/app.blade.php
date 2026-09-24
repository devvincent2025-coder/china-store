<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- viewport-fit=cover lets the app draw edge-to-edge on notched/rounded-corner phones —
            paired with env(safe-area-inset-*) padding (see resources/css/app.css and
            Components/MobileTabBar.jsx) so content and the bottom tab bar don't sit under the
            notch/home-indicator when installed as a PWA. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- PWA: installable app metadata. See public/manifest.json, public/sw.js (service worker),
            and Components/InstallAppModal.jsx (the custom install prompt UI, since Chrome/Android's
            native beforeinstallprompt has no built-in UI of its own, and iOS Safari has no
            programmatic install API at all). --}}
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#EA580C">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="ChinaInNigeria">
        <link rel="apple-touch-icon" href="/images/pwa/apple-touch-icon.png">

        {{-- Server-rendered title/meta/canonical/hreflang/OpenGraph/JSON-LD, built from
            $page['props']['seoDefaults'] + $page['props']['seo'] — see
            resources/views/partials/seo-meta.blade.php and App\Support\Seo\Seo. This app has no
            Inertia SSR pipeline, so this Blade-side render is what a crawler or link-preview bot
            (Googlebot, WhatsApp, Facebook, Twitter — none of which reliably execute JS) actually
            sees. The Inertia head directive further down reconciles these into the same elements
            on the client (matched via the "inertia" attribute) rather than duplicating them, and
            takes over from there for SPA navigations to a different page. --}}
        @include('partials.seo-meta')

        <!-- Favicon: the truck-through-gate icon mark (see /logo in the repo root for source files) -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <!-- Fonts: Urbanist (brand/core) + Manrope (UI accents) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700|urbanist:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
