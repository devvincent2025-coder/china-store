<?php

namespace App\Services\Seeding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Looks up real product / promo photos by search phrase during seeding.
 * Primary source: Wikimedia Commons (public domain / freely licensed media).
 * Fallback: Openverse (CC-licensed Flickr & friends).
 *
 * Not used at request time — only by database seeders.
 */
class ProductImageSearchService
{
    /** @var array<string, list<array{url: string, title: string}>> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $usedUrls = [];

    private float $lastRequestAt = 0;

    public function find(string $query, int $offset = 0, ?string $fallbackQuery = null): string
    {
        $candidates = $this->candidateQueries($query, $fallbackQuery);

        foreach ($candidates as $candidate) {
            $results = $this->search($candidate);
            $picked = $this->pick($results, $candidate, $offset);
            if ($picked !== null) {
                $this->usedUrls[$picked] = true;

                return $picked;
            }
        }

        return 'https://picsum.photos/seed/'.Str::slug($query ?: 'product').'-'.$offset.'/800/800';
    }

    public function findWide(string $query, int $offset = 0, ?string $fallbackQuery = null): string
    {
        $url = $this->find($query, $offset, $fallbackQuery);

        if (str_contains($url, 'picsum.photos')) {
            return 'https://picsum.photos/seed/'.Str::slug($query).'-banner-'.$offset.'/1600/600';
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function candidateQueries(string $query, ?string $fallbackQuery): array
    {
        $queries = [];

        // Short noun-ish query first — Wikimedia returns nothing for long marketing titles.
        $short = $this->shorten($query, 4);
        if ($short !== '') {
            $queries[] = $short;
        }

        $shorter = $this->shorten($query, 2);
        if ($shorter !== '' && $shorter !== $short) {
            $queries[] = $shorter;
        }

        $trimmed = trim($query);
        if ($trimmed !== '' && ! in_array($trimmed, $queries, true)) {
            $queries[] = $trimmed;
        }

        if ($fallbackQuery) {
            $fbShort = $this->shorten($fallbackQuery, 3);
            if ($fbShort !== '' && ! in_array($fbShort, $queries, true)) {
                $queries[] = $fbShort;
            }
            if (! in_array($fallbackQuery, $queries, true)) {
                $queries[] = $fallbackQuery;
            }
        }

        return $queries;
    }

    /**
     * @return list<array{url: string, title: string}>
     */
    private function search(string $query): array
    {
        $key = Str::lower(trim($query));

        if ($key === '') {
            return [];
        }

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $results = $this->searchWikimedia($query);

        if ($results === []) {
            $results = $this->searchOpenverse($query);
        }

        $this->cache[$key] = $results;

        return $results;
    }

    /**
     * @param  list<array{url: string, title: string}>  $results
     */
    private function pick(array $results, string $query, int $offset): ?string
    {
        if ($results === []) {
            return null;
        }

        $ranked = $this->rank($results, $query);

        // Prefer unused images that still match the query.
        $unused = array_values(array_filter(
            $ranked,
            fn (array $row) => ! isset($this->usedUrls[$row['url']])
        ));

        $pool = $unused !== [] ? $unused : $ranked;

        return $pool[$offset % count($pool)]['url'] ?? null;
    }

    /**
     * @param  list<array{url: string, title: string}>  $results
     * @return list<array{url: string, title: string, score: int}>
     */
    private function rank(array $results, string $query): array
    {
        $terms = $this->terms($query);

        $scored = [];
        foreach ($results as $row) {
            $haystack = Str::lower($row['title'].' '.$row['url']);
            $score = 0;
            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    $score += 3;
                }
            }
            // Mild preference for jpeg product photos over random documents.
            if (str_contains($haystack, '.jpg') || str_contains($haystack, 'jpeg')) {
                $score += 1;
            }
            $scored[] = $row + ['score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Drop completely irrelevant Openverse hits (score 0) when anything scored higher exists.
        $best = $scored[0]['score'] ?? 0;
        if ($best > 0) {
            $scored = array_values(array_filter($scored, fn ($row) => $row['score'] > 0));
        }

        return $scored;
    }

    /**
     * @return list<array{url: string, title: string}>
     */
    private function searchWikimedia(string $query): array
    {
        $this->throttle();

        $response = Http::withHeaders([
            'User-Agent' => 'ChinaInNigeriaSeeder/1.0 (local marketplace demo seeding)',
            'Accept' => 'application/json',
        ])
            ->timeout(20)
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'generator' => 'search',
                'gsrsearch' => $query,
                'gsrnamespace' => 6,
                'gsrlimit' => 10,
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime|size',
                'iiurlwidth' => 800,
            ]);

        if ($response->failed()) {
            return [];
        }

        $pages = $response->json('query.pages') ?? [];
        $results = [];

        foreach ($pages as $page) {
            $info = ($page['imageinfo'] ?? [])[0] ?? null;
            if (! $info) {
                continue;
            }

            $mime = (string) ($info['mime'] ?? '');
            if (! str_starts_with($mime, 'image/') || str_contains($mime, 'svg')) {
                continue;
            }

            $url = $info['thumburl'] ?? $info['url'] ?? null;
            if (is_string($url) && $url !== '') {
                $results[] = [
                    'url' => $this->cleanUrl($url),
                    'title' => (string) ($page['title'] ?? ''),
                ];
            }
        }

        return $results;
    }

    /**
     * @return list<array{url: string, title: string}>
     */
    private function searchOpenverse(string $query): array
    {
        $this->throttle();

        $response = Http::withHeaders([
            'User-Agent' => 'ChinaInNigeriaSeeder/1.0 (local marketplace demo seeding)',
            'Accept' => 'application/json',
        ])
            ->timeout(20)
            ->get('https://api.openverse.org/v1/images/', [
                'q' => $query,
                'page_size' => 10,
                'license_type' => 'commercial,modification',
            ]);

        if ($response->failed()) {
            return [];
        }

        $results = [];
        foreach ($response->json('results') ?? [] as $result) {
            $url = $result['url'] ?? null;
            if (is_string($url) && $url !== '') {
                $results[] = [
                    'url' => $this->cleanUrl($url),
                    'title' => (string) ($result['title'] ?? ''),
                ];
            }
        }

        return $results;
    }

    private function shorten(string $query, int $limit = 4): string
    {
        $words = preg_split('/\s+/', trim($query)) ?: [];
        $stop = [
            'with', 'and', 'for', 'the', 'a', 'an', 'of', 'set', 'pieces', 'pack',
            'piece', 'from', 'into', 'non', 'free', 'built', 'in', 'multiple',
            'speed', 'settings', 'portable', 'universal', 'classic', 'compact',
            'fast', 'charging', 'automatic', 'adjustable', 'soft', 'colorful',
            'natural', 'rechargeable', 'educational', 'foldable', 'hydrating',
        ];

        $kept = [];
        foreach ($words as $word) {
            $clean = Str::lower(preg_replace('/[^a-zA-Z0-9\-]/', '', $word) ?? '');
            if ($clean === '' || in_array($clean, $stop, true) || is_numeric($clean) || strlen($clean) < 3) {
                continue;
            }
            // Skip pure size/spec tokens like 20000mah, 1.8l unless nothing else kept yet.
            if (preg_match('/^\d/', $clean)) {
                continue;
            }
            $kept[] = $clean;
            if (count($kept) >= $limit) {
                break;
            }
        }

        return implode(' ', $kept);
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        return array_values(array_filter(explode(' ', $this->shorten($query, 6))));
    }

    private function cleanUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $cleaned = $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '');

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $keep = array_intersect_key($query, array_flip(['width', 'height', 'quality', 'fit']));
            if ($keep !== []) {
                $cleaned .= '?'.http_build_query($keep);
            }
        }

        return $cleaned;
    }

    private function throttle(): void
    {
        $now = microtime(true);
        $wait = 0.3 - ($now - $this->lastRequestAt);
        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }
        $this->lastRequestAt = microtime(true);
    }
}
