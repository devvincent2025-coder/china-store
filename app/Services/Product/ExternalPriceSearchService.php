<?php

namespace App\Services\Product;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * NOT CURRENTLY WIRED UP — ComparePricesController does not call this. Kept as a working scaffold
 * in case a future decision picks one of the options below; do not re-enable it by just calling
 * searchPricierThan() again without addressing why it was disabled first.
 *
 * This was meant to look up what other Nigerian online stores charge for a product by reading
 * their own public search-results pages directly (no third-party API). In practice, every plain
 * server-side HTTP request path was tested and blocked:
 *   - Jumia (jumia.com.ng/catalog): returns a Cloudflare bot-challenge page (HTTP 403, "Just a
 *     moment...") to any non-browser request — no product data is reachable without solving a JS
 *     challenge.
 *   - Konga (konga.com/search): returns HTTP 200, but it's a client-hydrated Next.js app — actual
 *     product/price data is streamed in as JS payloads, not present in the raw HTML a plain HTTP
 *     client sees (confirmed: only 3 "₦" occurrences on the whole page, all static filter-UI copy).
 *   - Scraping Google's own search results (google.com/search) directly was also tried and hits the
 *     same wall — Google returns a JS-required redirect page to non-browser requests, and doing
 *     this at any real volume would violate Google's Terms of Service regardless.
 *
 * None of these are fixable by adjusting a CSS selector or regex — they'd need a real headless
 * browser (Playwright/Puppeteer, its own service + hosting cost) to render JS and/or get past bot
 * detection, or a paid scraping/search API (ScraperAPI, Bright Data, SerpApi) that handles that for
 * you. Compare Prices currently shows vendor listings on this platform only.
 */
class ExternalPriceSearchService
{
    private const CACHE_TTL_HOURS = 12;

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * One entry per retailer scraped: how to build the search URL for a query, and how to pull
     * (title, price-text) pairs out of the resulting HTML via a CSS selector. Adding a new retailer
     * is just adding an entry here — nothing else in this class is retailer-specific.
     *
     * @var array<int, array{name: string, url: callable(string): string, item_selector: string, title_selector: string, price_selector: string}>
     */
    private array $sources;

    public function __construct()
    {
        $this->sources = [
            [
                'name' => 'Jumia',
                'url' => fn (string $q) => 'https://www.jumia.com.ng/catalog/?q='.urlencode($q),
                'item_selector' => 'article.prd',
                'title_selector' => '.name',
                'price_selector' => '.prc',
            ],
            [
                'name' => 'Konga',
                'url' => fn (string $q) => 'https://www.konga.com/search?search='.urlencode($q),
                'item_selector' => '[data-testid="product-card"]',
                'title_selector' => '[data-testid="product-name"]',
                'price_selector' => '[data-testid="product-price"]',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        // Always "configured" — this reads public pages directly rather than depending on any API
        // key. Kept as a method (rather than removed) so the controller's call site doesn't change
        // if a future source ever needs credentials.
        return true;
    }

    /**
     * @return array<int, array{title: string, link: string, source: string, price_kobo: int, price_formatted: string}>
     */
    public function searchPricierThan(string $productTitle, int $ourLowestPriceKobo): array
    {
        if (trim($productTitle) === '') {
            return [];
        }

        $results = Cache::remember(
            'external-price-search:'.md5(strtolower($productTitle)),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn () => $this->fetchAll($productTitle),
        );

        return collect($results)
            ->filter(fn (array $result) => $result['price_kobo'] > $ourLowestPriceKobo)
            ->sortBy('price_kobo')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: string, link: string, source: string, price_kobo: int, price_formatted: string}>
     */
    private function fetchAll(string $productTitle): array
    {
        $results = [];

        foreach ($this->sources as $source) {
            try {
                $results = array_merge($results, $this->fetchFromSource($source, $productTitle));
            } catch (\Throwable $e) {
                // A single retailer's markup changing (or the site being briefly down) should never
                // take down the whole comparison feature — just skip that source for this search.
                Log::warning("External price search: {$source['name']} lookup failed", ['message' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * @param  array{name: string, url: callable(string): string, item_selector: string, title_selector: string, price_selector: string}  $source
     * @return array<int, array{title: string, link: string, source: string, price_kobo: int, price_formatted: string}>
     */
    private function fetchFromSource(array $source, string $productTitle): array
    {
        $url = ($source['url'])($productTitle);

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(6)
            ->get($url);

        if (! $response->successful()) {
            return [];
        }

        $crawler = new Crawler($response->body());
        $listings = [];

        $crawler->filter($source['item_selector'])->slice(0, 20)->each(function (Crawler $node) use ($source, $url, &$listings) {
            try {
                $titleNode = $node->filter($source['title_selector']);
                $priceNode = $node->filter($source['price_selector']);

                if ($titleNode->count() === 0 || $priceNode->count() === 0) {
                    return;
                }

                $priceKobo = $this->extractPriceKobo($priceNode->first()->text());

                if ($priceKobo === null) {
                    return;
                }

                $link = $node->filter('a')->count() > 0 ? $node->filter('a')->first()->attr('href') : null;

                $listings[] = [
                    'title' => trim($titleNode->first()->text()),
                    'link' => $this->absoluteUrl($link, $url),
                    'source' => $source['name'],
                    'price_kobo' => $priceKobo,
                    'price_formatted' => '₦'.number_format($priceKobo / 100, 2),
                ];
            } catch (\Throwable) {
                // Malformed individual card — skip it, don't fail the whole page's results.
            }
        });

        return $listings;
    }

    private function absoluteUrl(?string $link, string $pageUrl): string
    {
        if (! $link) {
            return $pageUrl;
        }

        if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
            return $link;
        }

        $parts = parse_url($pageUrl);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').'/'.ltrim($link, '/');
    }

    /**
     * Extracts a Naira amount from a retailer's own price element text (e.g. "₦ 12,345" or
     * "N15,000.00") — these are far more reliable than parsing a Google snippet since the text
     * node itself IS the price, not incidental surrounding copy.
     */
    private function extractPriceKobo(string $text): ?int
    {
        if (preg_match('/([\d]{1,3}(?:,\d{3})+(?:\.\d{2})?|\d{3,}(?:\.\d{2})?)/', $text, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);

            if ($amount < 100) {
                return null;
            }

            return (int) round($amount * 100);
        }

        return null;
    }
}
