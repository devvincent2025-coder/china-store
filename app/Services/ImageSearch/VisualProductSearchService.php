<?php

namespace App\Services\ImageSearch;

use App\Models\Product;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Finds published products whose primary images look closest to an uploaded query photo.
 * Uses cached color/histogram fingerprints so repeated searches stay fast.
 */
class VisualProductSearchService
{
    public function __construct(
        private readonly ImageFingerprintService $fingerprints,
    ) {}

    /**
     * @return Collection<int, Product>
     */
    public function search(string $queryImagePath, int $limit = 24): Collection
    {
        $queryFp = $this->fingerprints->fromPath($queryImagePath);
        if ($queryFp === null) {
            return collect();
        }

        $products = Product::query()
            ->publiclyListed()
            ->with(['images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
            ->limit(120)
            ->get();

        $this->prefetchRemoteFingerprints($products);

        $ranked = [];

        foreach ($products as $product) {
            $image = $product->images->first();
            $url = $image?->enhanced_url ?? $image?->original_url;
            if (! $url) {
                continue;
            }

            $fp = $this->fingerprintForUrl($url);
            if ($fp === null) {
                continue;
            }

            $ranked[] = [
                'product' => $product,
                'score' => $this->fingerprints->distance($queryFp, $fp),
            ];
        }

        usort($ranked, fn ($a, $b) => $a['score'] <=> $b['score']);

        return collect($ranked)
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /**
     * Download and fingerprint missing remote catalog images in parallel (cold cache).
     *
     * @param  Collection<int, Product>  $products
     */
    private function prefetchRemoteFingerprints(Collection $products): void
    {
        $pending = [];

        foreach ($products as $product) {
            $image = $product->images->first();
            $url = $image?->enhanced_url ?? $image?->original_url;
            if (! $url || (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://'))) {
                continue;
            }
            if (is_array(Cache::get('img_fp:'.sha1($url)))) {
                continue;
            }
            $pending[$url] = $url;
        }

        foreach (array_chunk(array_values($pending), 12) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => collect($chunk)->map(
                fn (string $url) => $pool->as($url)
                    ->timeout(5)
                    ->withHeaders(['User-Agent' => 'ChinaInNigeriaImageSearch/1.0'])
                    ->get($url)
            )->all());

            foreach ($chunk as $url) {
                $response = $responses[$url] ?? null;
                if (! $response instanceof Response || $response->failed()) {
                    continue;
                }

                $tmp = sys_get_temp_dir().'/cin_img_'.uniqid('', true).'.img';
                try {
                    file_put_contents($tmp, $response->body());
                    $fp = $this->fingerprints->fromPath($tmp);
                    if ($fp !== null) {
                        Cache::put('img_fp:'.sha1($url), $fp, now()->addDays(7));
                    }
                } finally {
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            }
        }
    }

    /**
     * @return array{r: float, g: float, b: float, buckets: list<float>}|null
     */
    private function fingerprintForUrl(string $url): ?array
    {
        $cacheKey = 'img_fp:'.sha1($url);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        // Remote URLs should already be handled by prefetchRemoteFingerprints().
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return null;
        }

        $path = ltrim(preg_replace('#^/storage/#', '', parse_url($url, PHP_URL_PATH) ?: $url), '/');
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $tmp = sys_get_temp_dir().'/cin_img_'.uniqid('', true).'.img';
        try {
            file_put_contents($tmp, Storage::disk('public')->get($path));
            $fp = $this->fingerprints->fromPath($tmp);
            if ($fp !== null) {
                Cache::put($cacheKey, $fp, now()->addDays(7));
            }

            return $fp;
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
