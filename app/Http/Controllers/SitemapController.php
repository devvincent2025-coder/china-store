<?php

namespace App\Http\Controllers;

use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic, DB-driven sitemap, split into an index + per-type sub-sitemaps so the product catalog
 * can grow well past what fits in one file (Google's limit is 50k URLs / 50MB per file). What gets
 * listed, and why, lives in App\Support\Seo\SitemapBuilder — shared with the `sitemap:export`
 * command, which also re-warms these cache entries (see CACHE_KEY_PREFIX).
 */
class SitemapController extends Controller
{
    public const CACHE_KEY_PREFIX = 'sitemap.';

    public const CACHE_TTL_SECONDS = 21600; // 6 hours — a sitemap doesn't need to be second-fresh.

    public function __construct(private readonly SitemapBuilder $builder) {}

    public function index(): Response
    {
        return $this->cached('index', fn () => $this->builder->index());
    }

    public function staticPages(): Response
    {
        return $this->cached('static', fn () => $this->builder->staticPages());
    }

    public function categories(): Response
    {
        return $this->cached('categories', fn () => $this->builder->categories());
    }

    public function stores(): Response
    {
        return $this->cached('stores', fn () => $this->builder->stores());
    }

    public function products(int $page): Response
    {
        abort_if($page < 1 || $page > $this->builder->productPageCount(), 404);

        return $this->cached("products.{$page}", fn () => $this->builder->products($page));
    }

    private function cached(string $key, callable $build): Response
    {
        $xml = Cache::remember(self::CACHE_KEY_PREFIX.$key, self::CACHE_TTL_SECONDS, $build);

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
