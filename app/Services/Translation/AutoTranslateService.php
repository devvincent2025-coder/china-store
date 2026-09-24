<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates vendor product copy between zh / en / fr.
 * Primary provider: MyMemory. Failures are retried and never cached.
 */
class AutoTranslateService
{
    /** App locale => provider language codes to try (first success wins). */
    private const LANG_CANDIDATES = [
        'zh' => ['zh-CN', 'zh-Hans', 'zh'],
        'en' => ['en'],
        'fr' => ['fr'],
    ];

    private const CHUNK_LIMIT = 450;

    private const CACHE_VERSION = 'v2';

    /**
     * @param  list<string>  $targets
     * @return array{translations: array<string, string>, failed: list<string>}
     */
    public function translate(string $text, string $from, array $targets): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['translations' => [], 'failed' => []];
        }

        $from = $this->normalize($from);
        if ($from === null) {
            return ['translations' => [], 'failed' => array_values($targets)];
        }

        $translations = [];
        $failed = [];

        foreach ($targets as $to) {
            $to = $this->normalize($to);
            if ($to === null || $to === $from) {
                continue;
            }

            $translated = $this->translateText($text, $from, $to);

            // One retry after a short pause — MyMemory intermittently drops zh pairs.
            if ($translated === null || $translated === '') {
                usleep(250_000);
                $translated = $this->translateText($text, $from, $to, bypassCache: true);
            }

            if ($translated !== null && $translated !== '') {
                $translations[$to] = $translated;
            } else {
                $failed[] = $to;
            }
        }

        return [
            'translations' => $translations,
            'failed' => $failed,
        ];
    }

    private function translateText(string $text, string $from, string $to, bool $bypassCache = false): ?string
    {
        $chunks = $this->chunk($text);
        $parts = [];

        foreach ($chunks as $chunk) {
            $translated = $this->translateOne($chunk, $from, $to, $bypassCache);
            if ($translated === null || $translated === '') {
                return null;
            }
            $parts[] = $translated;
        }

        return trim(implode(preg_match('/\R/u', $text) ? "\n" : ' ', $parts));
    }

    /**
     * @return list<string>
     */
    private function chunk(string $text): array
    {
        if (mb_strlen($text) <= self::CHUNK_LIMIT) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining) > self::CHUNK_LIMIT) {
            $slice = mb_substr($remaining, 0, self::CHUNK_LIMIT);
            $breakAt = $this->findBreak($slice);

            $chunks[] = trim(mb_substr($remaining, 0, $breakAt));
            $remaining = trim(mb_substr($remaining, $breakAt));
        }

        if ($remaining !== '') {
            $chunks[] = $remaining;
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    private function findBreak(string $slice): int
    {
        $candidates = ['。', "\n", '. ', '！', '？', '，', ', ', ' ', '、'];
        $best = 0;

        foreach ($candidates as $needle) {
            $pos = mb_strrpos($slice, $needle);
            if ($pos !== false && $pos > $best) {
                $best = $pos + mb_strlen($needle);
            }
        }

        return $best >= 40 ? $best : mb_strlen($slice);
    }

    private function translateOne(string $text, string $from, string $to, bool $bypassCache = false): ?string
    {
        $cacheKey = self::CACHE_VERSION.':auto_tr:'.hash('sha256', $from.'|'.$to.'|'.mb_strtolower($text));

        if (! $bypassCache) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $translated = $this->fetchFromMyMemory($text, $from, $to);

        // Only cache successes — never remember nulls/timeouts (that caused empty zh forever).
        if ($translated !== null && $translated !== '') {
            Cache::put($cacheKey, $translated, now()->addDays(14));
        }

        return $translated;
    }

    private function fetchFromMyMemory(string $text, string $from, string $to): ?string
    {
        $fromCodes = self::LANG_CANDIDATES[$from] ?? [$from];
        $toCodes = self::LANG_CANDIDATES[$to] ?? [$to];

        foreach ($fromCodes as $fromCode) {
            foreach ($toCodes as $toCode) {
                $result = $this->requestMyMemory($text, $fromCode, $toCode, $to);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    private function requestMyMemory(string $text, string $fromCode, string $toCode, string $appLocale): ?string
    {
        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => $fromCode.'|'.$toCode,
                ]);

            if ($response->failed()) {
                Log::info('MyMemory HTTP failure', [
                    'pair' => $fromCode.'|'.$toCode,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $status = (int) $response->json('responseStatus', 0);
            if ($status !== 200 && $status !== 0) {
                return null;
            }

            $translated = $response->json('responseData.translatedText');

            if (! is_string($translated) || trim($translated) === '') {
                return null;
            }

            $translated = html_entity_decode(trim($translated), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $upper = strtoupper($translated);

            if (str_contains($upper, 'INVALID') && str_contains($upper, 'LANGUAGE')) {
                return null;
            }
            if (str_contains($upper, 'MYMEMORY WARNING') || str_contains($upper, 'QUERY LENGTH LIMIT')) {
                return null;
            }

            // Reject no-op / clearly wrong Chinese results (Latin-only when target is zh).
            if ($appLocale === 'zh' && ! preg_match('/[\x{4e00}-\x{9fff}]/u', $translated)) {
                Log::info('MyMemory rejected non-Chinese result', [
                    'pair' => $fromCode.'|'.$toCode,
                    'sample' => mb_substr($translated, 0, 40),
                ]);

                return null;
            }

            // Reject identical copy for longer phrases (provider often echoes source on failure).
            if (mb_strtolower($translated) === mb_strtolower($text) && mb_strlen($text) > 12) {
                return null;
            }

            return $translated;
        } catch (\Throwable $e) {
            Log::warning('Auto-translate request failed', [
                'pair' => $fromCode.'|'.$toCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalize(string $locale): ?string
    {
        $locale = strtolower($locale);

        return array_key_exists($locale, self::LANG_CANDIDATES) ? $locale : null;
    }
}
