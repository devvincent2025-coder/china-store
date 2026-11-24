<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\Product;
use App\Models\VendorStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Single source of truth for every page's SEO payload. Assembles one plain array (title,
 * description, keywords, canonical, hreflang alternates, robots directive, OG/Twitter data, and
 * one-or-more schema.org JSON-LD blocks) that gets rendered TWICE from the exact same data:
 *
 *   1. Server-side, in resources/views/app.blade.php (reading $page['props']['seo']) — this is
 *      what a crawler or link-preview bot (Googlebot, WhatsApp, Twitter, Facebook) sees on the
 *      very first HTML response, before any JavaScript runs. This app has no Inertia SSR pipeline
 *      (see resources/js/ssr.jsx — doesn't exist), so this Blade-side render is the ONLY thing
 *      guaranteeing search engines and link-preview crawlers see real title/description/structured
 *      data rather than the bare app shell.
 *   2. Client-side, via resources/js/Components/SeoHead.jsx, so the browser tab title/meta stay
 *      correct across Inertia's SPA navigations (the Blade render only happens once, on the very
 *      first full-page load of a given URL).
 *
 * Every controller that wants full SEO treatment builds one of these via a `for*()` method below
 * and passes it as the `seo` Inertia prop. Site-wide defaults (Organization/WebSite JSON-LD,
 * fallback title/description/image) are shared globally instead — see
 * Http\Middleware\HandleInertiaRequests's `seoDefaults` prop — so every page gets *something*
 * correct even before it's been given bespoke treatment here.
 */
class Seo
{
    public const SITE_NAME = 'ChinaInNigeria';

    public const SUPPORTED_LOCALES = ['en', 'zh', 'fr'];

    // Falls back to the existing brand icon (public/images/branding/icon.png) since no dedicated
    // 1200×630 OG/Twitter-card banner exists yet — usable today, but small (240×200) and worth
    // replacing with a properly-sized designed banner for the best link-preview quality.
    public const DEFAULT_IMAGE = '/images/branding/icon.png';

    /**
     * The site-wide Organization + WebSite (with a sitelinks SearchAction) JSON-LD — safe to
     * render identically on every page; Google explicitly permits (and expects) this to be
     * consistent site-wide rather than only present on the homepage.
     */
    public static function organizationJsonLd(): array
    {
        $url = config('app.url');

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => self::SITE_NAME,
                'url' => $url,
                // Google's logo rich-result guidance wants a raster image (min 112x112), not SVG.
                'logo' => self::absoluteUrl(self::DEFAULT_IMAGE),
                'sameAs' => [],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => self::SITE_NAME,
                'url' => $url,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => $url.'/products?q={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $items  Must mirror Q&A content
     *   actually VISIBLE on the page (see Legal/CustomerProtection.jsx's FAQ section) — Google's
     *   guidelines treat FAQPage markup that doesn't match on-page content as spam.
     */
    public static function faqJsonLd(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($items)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ])->all(),
        ];
    }

    public static function breadcrumbJsonLd(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    public static function productJsonLd(Product $product, string $canonicalUrl): array
    {
        $storeName = $product->vendor?->store?->name
            ? $product->vendor->store->name
            : ($product->vendor?->business_name ?? self::SITE_NAME);

        $images = $product->images
            ->map(fn ($image) => $image->display_url)
            ->filter()
            ->values()
            ->all();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => Str::limit(strip_tags($product->description ?? ''), 5000, ''),
            'sku' => $product->sku ?: (string) $product->id,
            'image' => $images ?: [self::absoluteUrl(self::DEFAULT_IMAGE)],
            'brand' => [
                '@type' => 'Brand',
                'name' => $storeName,
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonicalUrl,
                'priceCurrency' => 'NGN',
                'price' => number_format($product->effective_price / 100, 2, '.', ''),
                'availability' => $product->isInStock()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $storeName,
                ],
            ],
        ];

        // withCount/withAvg aggregates (products index) vs. the already-eager-loaded `reviews`
        // relation (product show, see ProductController::show()) — prefer whichever is already in
        // memory over triggering a fresh query for either.
        $reviewCount = $product->reviews_count ?? ($product->relationLoaded('reviews') ? $product->reviews->count() : $product->reviews()->count());
        $avgRating = $product->reviews_avg_rating ?? ($product->relationLoaded('reviews') ? $product->reviews->avg('rating') : $product->average_rating);

        if ($reviewCount > 0 && $avgRating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($avgRating, 1),
                'reviewCount' => $reviewCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // A handful of individual Review nodes alongside the aggregate — Google accepts (and rich
        // results can show) either, but real reviewer names/comments make the listing feel less
        // like a bare number. Capped at 5 and only ones with an actual comment (a bare star rating
        // with no text makes for a thin, low-value Review node).
        if ($product->relationLoaded('reviews')) {
            $reviewNodes = $product->reviews
                ->filter(fn ($review) => filled($review->comment))
                ->sortByDesc('created_at')
                ->take(5)
                ->map(fn ($review) => [
                    '@type' => 'Review',
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review->rating,
                        'bestRating' => 5,
                        'worstRating' => 1,
                    ],
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review->user?->name ?? 'Verified Buyer',
                    ],
                    'reviewBody' => Str::limit($review->comment, 1000, ''),
                    'datePublished' => $review->created_at?->toDateString(),
                ])
                ->values()
                ->all();

            if ($reviewNodes) {
                $schema['review'] = $reviewNodes;
            }
        }

        return $schema;
    }

    /**
     * A product page is reachable (200) whenever its own status is 'published', but that's a
     * looser check than Product::scopePubliclyListed() (which also requires the vendor to be
     * active, not on vacation, and past KYC) — see ProductController::show(). A product that's
     * live but excluded from every listing/search/sitemap should not be indexed either, or Google
     * ends up with a "orphaned" page nothing else on the site links to or recommends.
     */
    public static function productIsIndexable(Product $product): bool
    {
        $vendor = $product->vendor;

        return $vendor
            && ! $vendor->vacation_mode
            && $vendor->status === 'active'
            && $vendor->identity_verification_status === 'approved'
            && $vendor->phone_verified_at !== null
            && $vendor->user?->email_verified_at !== null;
    }

    public static function forProduct(Request $request, Product $product): array
    {
        $canonical = route('products.show', $product);
        $title = $product->title;
        $storeName = $product->vendor?->store?->name
            ? $product->vendor->store->name
            : ($product->vendor?->business_name ?? self::SITE_NAME);
        $categoryName = $product->category?->name;
        $priceNaira = number_format($product->price / 100, 0);

        $description = self::truncateDescription(
            strip_tags($product->description ?? ''),
            "Buy {$title} online in Nigeria for ₦{$priceNaira} from {$storeName} — a verified vendor on ".self::SITE_NAME.'. Fast local delivery, secure escrow payment, buyer protection guaranteed.'
        );

        $keywords = self::keywords([
            $title,
            $categoryName,
            $storeName,
            'buy '.$title.' online nigeria',
            'chinese vendor nigeria',
            $categoryName ? $categoryName.' nigeria' : null,
            'naira',
            'verified vendor',
        ]);

        $breadcrumbs = array_filter([
            ['name' => 'Home', 'url' => route('home')],
            $product->category ? ['name' => $categoryName, 'url' => route('categories.show', $product->category)] : null,
            ['name' => $title, 'url' => $canonical],
        ]);

        return [
            'title' => "{$title} | Buy Online in Nigeria - ".self::SITE_NAME,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'image' => $product->images->first()?->display_url ?: self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'product',
            'robots' => self::productIsIndexable($product) ? 'index,follow' : 'noindex,follow',
            'alternates' => self::alternates($request),
            'product' => [
                'price' => number_format($product->effective_price / 100, 2, '.', ''),
                'currency' => 'NGN',
                'availability' => $product->isInStock() ? 'instock' : 'oos',
            ],
            'jsonLd' => [
                self::productJsonLd($product, $canonical),
                self::breadcrumbJsonLd($breadcrumbs),
            ],
        ];
    }

    public static function forCategory(Request $request, Category $category): array
    {
        $name = $category->name;
        $canonical = route('categories.show', $category);

        $description = "Shop {$name} products in Nigeria from verified Chinese-run vendors on ".self::SITE_NAME
            .'. Naira pricing, fast local delivery, and every order protected by escrow.';

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => $name, 'url' => $canonical],
        ];

        return [
            'title' => "{$name} Products in Nigeria - Shop Verified Vendors | ".self::SITE_NAME,
            'description' => Str::limit($description, 160, ''),
            'keywords' => self::keywords([$name, $name.' nigeria', 'buy '.$name.' online', 'chinese vendors nigeria', 'naira']),
            'canonical' => $canonical,
            'image' => $category->image_url ?: self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'website',
            'robots' => 'index,follow',
            'alternates' => self::alternates($request),
            'jsonLd' => [
                self::breadcrumbJsonLd($breadcrumbs),
            ],
        ];
    }

    public static function forVendorStore(Request $request, VendorStore $store): array
    {
        $name = $store->name;
        $description = $store->description;
        $canonical = route('vendors.show', $store);
        $location = trim(collect([$store->city, $store->state])->filter()->implode(', '));

        $metaDescription = self::truncateDescription(
            strip_tags($description ?? ''),
            "{$name} is a verified Chinese-run vendor on ".self::SITE_NAME.($location ? " based in {$location}" : '').'. Shop their catalog with Naira pricing, fast delivery, and buyer protection on every order.'
        );

        $breadcrumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Stores', 'url' => route('stores.index')],
            ['name' => $name, 'url' => $canonical],
        ];

        $storeJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $name,
            'url' => $canonical,
            'image' => $store->logo_url ?: $store->banner_url ?: self::absoluteUrl(self::DEFAULT_IMAGE),
            'description' => Str::limit(strip_tags($description ?? ''), 500, ''),
        ];

        if ($location) {
            $storeJsonLd['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $store->city,
                'addressRegion' => $store->state,
                'addressCountry' => 'NG',
            ];
        }

        return [
            'title' => "{$name} - Verified Chinese Vendor in Nigeria | ".self::SITE_NAME,
            'description' => $metaDescription,
            'keywords' => self::keywords([$name, 'chinese vendor nigeria', $location, 'verified vendor', 'naira']),
            'canonical' => $canonical,
            'image' => $store->logo_url ?: $store->banner_url ?: self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'website',
            'robots' => 'index,follow',
            'alternates' => self::alternates($request),
            'jsonLd' => [
                $storeJsonLd,
                self::breadcrumbJsonLd($breadcrumbs),
            ],
        ];
    }

    public static function forProductsIndex(Request $request): array
    {
        $term = $request->string('q')->toString();
        $canonical = self::absoluteUrl('/products');

        $title = match (true) {
            $term !== '' => "Search results for \"{$term}\" | ".self::SITE_NAME,
            default => 'Shop All Products in Nigeria - Verified Chinese Vendors | '.self::SITE_NAME,
        };

        $description = match (true) {
            $term !== '' => "Search results for \"{$term}\" on ".self::SITE_NAME.' — shop products from verified Chinese-run vendors across Nigeria, with Naira pricing and buyer protection on every order.',
            default => 'Browse thousands of products from verified Chinese-run vendors across Nigeria. Naira pricing, fast local delivery, and every order protected by escrow.',
        };

        return [
            'title' => $title,
            'description' => Str::limit($description, 160, ''),
            'keywords' => self::keywords(['shop online nigeria', 'chinese vendors nigeria', 'buy online naira', $term ?: null]),
            'canonical' => $canonical,
            'image' => self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'website',
            'robots' => 'index,follow',
            'alternates' => self::alternates($request),
            'jsonLd' => [],
        ];
    }

    public static function forHome(Request $request): array
    {
        $canonical = self::absoluteUrl('/');

        return [
            'title' => self::SITE_NAME.' - Chinese Stores in Nigeria | Verified Vendors, Fast Delivery, Naira Prices',
            'description' => 'Shop Chinese-run stores already based in Nigeria. Verified vendors, Naira pricing, fast local delivery, and every order protected by escrow until you confirm you\'re happy.',
            'keywords' => self::keywords([
                'chinainnigeria',
                'chinese stores in nigeria',
                'chinese products in nigeria',
                'chinese vendors nigeria',
                'chinese sellers nigeria',
                'shop online nigeria',
                'buy online naira',
                'verified vendors nigeria',
                'buy chinese products online nigeria',
            ]),
            'canonical' => $canonical,
            'image' => self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'website',
            'robots' => 'index,follow',
            'alternates' => self::alternates($request),
            // Not organizationJsonLd() here — seoDefaults (shared on every request, see
            // HandleInertiaRequests) already contributes it, and the Blade/SeoHead renderers both
            // CONCATENATE seoDefaults.jsonLd with this page's jsonLd rather than replacing it, so
            // returning it again here would render Organization/WebSite twice on the homepage.
            'jsonLd' => [self::faqJsonLd(self::homeFaqs())],
        ];
    }

    /**
     * Single source of truth for the homepage FAQ: HomeController passes this same array to
     * Home.jsx as a `faqs` prop (rendered as real, visible on-page content) so it stays byte-for-byte
     * in sync with the FAQPage JSON-LD built from it here — see faqJsonLd()'s doc comment on why a
     * mismatch between the two would violate Google's FAQPage guidelines.
     */
    public static function homeFaqs(): array
    {
        // Admin-edited FAQ section (Filament → Homepage sections) wins, in the current locale with
        // English fallback; the built-in list below is only used if that section is missing/empty.
        $section = \App\Models\HomeSection::query()->live()->where('type', 'faq')->orderBy('sort_order')->first();
        $locale = app()->getLocale();
        $edited = collect($section?->settings['faqs'] ?? [])
            ->map(fn ($item) => [
                'question' => trim((string) (($item['question'][$locale] ?? null) ?: ($item['question']['en'] ?? ''))),
                'answer' => trim((string) (($item['answer'][$locale] ?? null) ?: ($item['answer']['en'] ?? ''))),
            ])
            ->filter(fn ($faq) => $faq['question'] !== '' && $faq['answer'] !== '')
            ->values()
            ->all();

        if ($edited !== []) {
            return $edited;
        }

        return [
            [
                'question' => 'Are there Chinese stores I can buy from directly in Nigeria?',
                'answer' => 'Yes. '.self::SITE_NAME.' hosts Chinese-run vendor stores already based in Nigeria, so you buy directly from a local seller and pay in Naira — no importing or waiting on international shipping.',
            ],
            [
                'question' => 'Is it safe to buy from Chinese vendors online in Nigeria?',
                'answer' => 'Every vendor on '.self::SITE_NAME.' goes through identity verification before they can sell, and every order is held in escrow and only released to the vendor once you confirm you\'re happy with what you received.',
            ],
            [
                'question' => 'How is delivery handled?',
                'answer' => 'Vendors ship from their local Nigerian base, so delivery times and costs are typically faster and cheaper than ordering directly from China.',
            ],
            [
                'question' => 'Do I pay in Naira?',
                'answer' => 'Yes, every price on '.self::SITE_NAME.' is listed and charged in Naira, with no foreign currency conversion or customs charges to worry about.',
            ],
        ];
    }

    /**
     * A simple, static-ish page (Kids, Stores index, legal pages, ...) that doesn't need a full
     * bespoke builder — just a title/description/keywords with the standard canonical/alternates/
     * robots treatment layered on.
     */
    public static function generic(Request $request, string $title, string $description, array $keywordParts = [], array $jsonLd = []): array
    {
        return [
            'title' => "{$title} | ".self::SITE_NAME,
            'description' => Str::limit($description, 160, ''),
            'keywords' => self::keywords($keywordParts),
            'canonical' => self::absoluteUrl($request->path() === '/' ? '/' : $request->path()),
            'image' => self::absoluteUrl(self::DEFAULT_IMAGE),
            'type' => 'website',
            'robots' => 'index,follow',
            'alternates' => self::alternates($request),
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * Same path/query, each of the three supported locales, with the ?lang= param swapped and an
     * x-default pointing at the clean (no-param) URL — see SetLocaleFromUser for how ?lang= drives
     * the actual locale (there's no path-based i18n like /zh/products/... in this app).
     */
    public static function alternates(Request $request): array
    {
        $query = $request->query();
        unset($query['lang']);
        $path = $request->path() === '/' ? '' : $request->path();
        $base = self::absoluteUrl('/'.$path);
        $queryString = http_build_query($query);
        $withQuery = fn (array $q) => $base.'?'.http_build_query($q);

        $alternates = ['x-default' => $queryString ? $withQuery($query) : $base];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $alternates[$locale] = $withQuery(array_merge($query, ['lang' => $locale]));
        }

        return $alternates;
    }

    /**
     * Same idea as alternates(), but for building hreflang variants of a known, already-absolute
     * URL (e.g. inside SitemapController, which isn't handling a real inbound request for each
     * product it lists) rather than the current request's own path/query.
     */
    public static function hreflangUrlsForUrl(string $url): array
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        $alternates = ['x-default' => $url];

        foreach (self::SUPPORTED_LOCALES as $locale) {
            $alternates[$locale] = $url.$separator.'lang='.$locale;
        }

        return $alternates;
    }

    public static function keywords(array $parts): string
    {
        return collect($parts)
            ->filter()
            ->map(fn ($p) => strtolower(trim($p)))
            ->unique()
            ->take(15)
            ->implode(', ');
    }

    public static function absoluteUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    private static function truncateDescription(string $sourceText, string $fallback): string
    {
        $text = trim($sourceText) !== '' ? $sourceText : $fallback;

        return Str::limit($text, 160, '');
    }
}
