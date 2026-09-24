<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes every sitemap document (index + static/categories/stores/products-N) to disk, and
 * re-warms SitemapController's cache with the exact same XML so the live /sitemap.xml routes
 * reflect today's catalog immediately rather than up to CACHE_TTL_SECONDS late.
 *
 * Exports to exports/sitemap/ by default — deliberately NOT public/, since a static
 * public/sitemap.xml would be served by the web server ahead of the route and silently go stale
 * between exports. The exported copy is for submitting/inspecting (Search Console, Bing Webmaster,
 * an SEO audit tool), not for serving.
 */
class ExportSitemap extends Command
{
    protected $signature = 'sitemap:export
        {--path= : Directory to write into (default: exports/sitemap)}
        {--gzip : Also write a .xml.gz copy of each file}
        {--no-warm : Skip refreshing the live sitemap cache}';

    protected $description = 'Generate the full sitemap set, export it to disk, and refresh the live sitemap cache';

    public function handle(SitemapBuilder $builder): int
    {
        $directory = $this->option('path') ?: base_path('exports/sitemap');
        File::ensureDirectoryExists($directory);

        // Drop product pages left over from a previous, larger catalog.
        foreach (File::glob($directory.'/sitemap-products-*.xml*') as $stale) {
            File::delete($stale);
        }

        $documents = $builder->all();
        $urlCount = 0;
        $imageCount = 0;

        foreach ($documents as $filename => $xml) {
            File::put("{$directory}/{$filename}", $xml);

            if ($this->option('gzip')) {
                File::put("{$directory}/{$filename}.gz", gzencode($xml, 9));
            }

            if (! $this->option('no-warm')) {
                Cache::put(SitemapController::CACHE_KEY_PREFIX.$this->cacheKey($filename), $xml, SitemapController::CACHE_TTL_SECONDS);
            }

            $urls = substr_count($xml, '<url>');
            $images = substr_count($xml, '<image:image>');
            $urlCount += $urls;
            $imageCount += $images;

            $this->line(sprintf('  %-28s %6d urls %6d images', $filename, $urls, $images));
        }

        $this->info(sprintf('Exported %d sitemap file(s) — %d URLs, %d images — to %s', count($documents), $urlCount, $imageCount, $directory));

        return self::SUCCESS;
    }

    /** sitemap.xml → index, sitemap-products-3.xml → products.3 (matching SitemapController). */
    private function cacheKey(string $filename): string
    {
        $name = Str::of($filename)->beforeLast('.xml');

        return match (true) {
            $name->is('sitemap') => 'index',
            $name->startsWith('sitemap-products-') => 'products.'.$name->after('sitemap-products-'),
            default => (string) $name->after('sitemap-'),
        };
    }
}
