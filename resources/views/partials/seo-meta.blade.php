@php
    // $page is the same array Inertia already exposes to app.blade.php (see its own use of
    // $page['component'] for @vite) — merging seoDefaults (site-wide, from
    // Http\Middleware\HandleInertiaRequests) with the page's own `seo` prop (from
    // App\Support\Seo\Seo::for*(), set per-controller). jsonLd is concatenated rather than
    // overwritten so the sitewide Organization/WebSite schema still renders alongside whatever a
    // page adds (Product, BreadcrumbList, Store, ...).
    $seoDefaults = $page['props']['seoDefaults'] ?? [];
    $pageSeo = $page['props']['seo'] ?? [];
    $seo = array_merge($seoDefaults, $pageSeo);
    $jsonLdBlocks = array_merge($seoDefaults['jsonLd'] ?? [], $pageSeo['jsonLd'] ?? []);
@endphp
<title inertia>{{ $seo['title'] ?? config('app.name') }}</title>
<meta inertia="description" name="description" content="{{ $seo['description'] ?? '' }}">
@if(! empty($seo['keywords']))
<meta inertia="keywords" name="keywords" content="{{ $seo['keywords'] }}">
@endif
<meta inertia="robots" name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">
@if(! empty($seo['canonical']))
<link inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
@endif

{{-- hreflang alternates — see App\Support\Seo\Seo::alternates(). Absent on pages that haven't
    been given per-page SEO treatment yet; harmless to omit (Google just treats the page as
    having no known alternates). --}}
@foreach(($seo['alternates'] ?? []) as $hreflang => $url)
<link inertia="alternate:{{ $hreflang }}" rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
@endforeach

{{-- OpenGraph — WhatsApp/Facebook/Twitter link previews read these from the RAW html, never
    executing JS, which is exactly why this Blade-side render (not just the client Head component)
    is what makes link sharing actually show a title/image/price in Nigeria's WhatsApp-heavy usage. --}}
<meta inertia="og:site_name" property="og:site_name" content="{{ $seo['siteName'] ?? config('app.name') }}">
<meta inertia="og:title" property="og:title" content="{{ $seo['title'] ?? config('app.name') }}">
<meta inertia="og:description" property="og:description" content="{{ $seo['description'] ?? '' }}">
<meta inertia="og:type" property="og:type" content="{{ $seo['type'] ?? 'website' }}">
@if(! empty($seo['canonical']))
<meta inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
@endif
@if(! empty($seo['image']))
<meta inertia="og:image" property="og:image" content="{{ $seo['image'] }}">
@endif
<meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
<meta inertia="twitter:title" name="twitter:title" content="{{ $seo['title'] ?? config('app.name') }}">
<meta inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] ?? '' }}">
@if(! empty($seo['image']))
<meta inertia="twitter:image" name="twitter:image" content="{{ $seo['image'] }}">
@endif

@if(! empty($seo['product']))
<meta inertia="product:price:amount" property="product:price:amount" content="{{ $seo['product']['price'] }}">
<meta inertia="product:price:currency" property="product:price:currency" content="{{ $seo['product']['currency'] }}">
@endif

@foreach($jsonLdBlocks as $index => $schema)
<script inertia="jsonld:{{ $index }}" type="application/ld+json">{!! str_replace('</script>', '<\/script>', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !!}</script>
@endforeach
