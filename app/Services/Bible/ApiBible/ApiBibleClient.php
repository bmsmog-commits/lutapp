<?php

namespace App\Services\Bible\ApiBible;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 27.8 — read-only client for API.Bible (https://api.bible).
 * Phase 27.9 — search, age-checked cache reads, FUMS provenance.
 *
 * Licensing obligations this class enforces (API.Bible fair-use terms):
 *  - Text is fetched one chapter (or ≤ 50 search hits) at a time, well under
 *    the 500-consecutive-verse caching limit, and cached for at most 14 days
 *    (MAX_CACHE_TTL) — enforced both by the store TTL and on every read.
 *  - Failed or empty responses are never cached.
 *  - Nothing is ever written to `bible_verses`; the local corpus is untouched.
 *  - Every chapter carries the publisher's copyright notice and FUMS token so
 *    the reader can display attribution and report the view.
 *  - Text comes only from the API response; nothing is generated or patched.
 */
class ApiBibleClient
{
    public function __construct(private readonly ApiBibleUsage $usage = new ApiBibleUsage)
    {
    }

    public const MAX_CACHE_TTL = 14 * 24 * 60 * 60;

    public const MAX_SEARCH_LIMIT = 50;

    // v2 (Phase 27.9): payloads carry `fetched_at`; v1 entries are ignored.
    private const CACHE_PREFIX = 'api_bible:v2:';

    private const COOLDOWN_KEY = 'api_bible:cooldown';

    public function isConfigured(): bool
    {
        return filled(config('services.api_bible.key'))
            && filled(config('services.api_bible.base_url'));
    }

    /**
     * The unfiltered catalog of Bibles this API key may access.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bibles(): array
    {
        return $this->get('/bibles')->json('data') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function bible(string $bibleId): array
    {
        return $this->get('/bibles/'.rawurlencode($bibleId))->json('data') ?? [];
    }

    /**
     * One chapter of a Bible, e.g. chapter('bba9f40183526463-01', 'JHN', 3).
     *
     * @throws ApiBibleException
     */
    public function chapter(string $bibleId, string $usfmBook, int $chapter): RemoteChapter
    {
        $chapterId = strtoupper($usfmBook).'.'.$chapter;
        $cacheKey = self::CACHE_PREFIX.'chapter:'.$bibleId.':'.$chapterId;

        if ($cached = $this->freshFromCache($cacheKey)) {
            return RemoteChapter::fromCache($cached);
        }

        $response = $this->get(
            '/bibles/'.rawurlencode($bibleId).'/chapters/'.rawurlencode($chapterId),
            [
                'content-type' => 'json',
                'include-notes' => 'false',
                'include-titles' => 'false',
                'include-chapter-numbers' => 'false',
                'include-verse-numbers' => 'false',
                'include-verse-spans' => 'false',
                'fums-version' => '3',
            ]
        );

        $verses = $this->extractVerses($response->json('data.content') ?? [], $chapterId);

        if ($verses->isEmpty()) {
            // A 200 with no verse text is treated as unavailable — the reader
            // shows its honest empty state rather than a blank chapter.
            throw new ApiBibleException(ApiBibleException::UNAVAILABLE, "API.Bible returned no verse text for {$chapterId}.");
        }

        $result = new RemoteChapter(
            $bibleId,
            $chapterId,
            (string) $response->json('data.reference', $chapterId),
            $verses,
            trim(strip_tags((string) $response->json('data.copyright', ''))),
            $this->fumsToken($response),
            now()->getTimestamp(),
        );

        Cache::put($cacheKey, $result->toCache(), $this->cacheTtl());

        return $result;
    }

    /**
     * Phase 27.9 — keyword search within one Bible (GET /bibles/{id}/search).
     * Verified against the live API on 2026-10-16: authorised for this key,
     * returns `verses` for word queries and `passages` for reference queries.
     *
     * @throws ApiBibleException
     */
    public function search(string $bibleId, string $query, int $offset = 0, int $limit = 20): RemoteSearchResult
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $offset = max(0, $offset);
        $limit = max(1, min($limit, self::MAX_SEARCH_LIMIT));
        $cacheKey = self::CACHE_PREFIX.'search:'.$bibleId.':'.sha1(mb_strtolower($query)).':'.$offset.':'.$limit;

        if ($cached = $this->freshFromCache($cacheKey)) {
            return RemoteSearchResult::fromCache($cached);
        }

        $response = $this->get('/bibles/'.rawurlencode($bibleId).'/search', [
            'query' => $query,
            'offset' => $offset,
            'limit' => $limit,
            'sort' => 'canonical',
            'fums-version' => '3',
        ]);

        $hits = collect($response->json('data.verses') ?? [])
            ->map(function ($verse) {
                $parsed = is_array($verse) ? RemoteSearchHit::parseVerseId((string) ($verse['id'] ?? '')) : null;
                $text = trim(strip_tags((string) ($verse['text'] ?? '')));

                return $parsed && $text !== ''
                    ? new RemoteSearchHit($parsed[0], $parsed[1], $parsed[2], (string) ($verse['reference'] ?? ''), $text)
                    : null;
            })
            ->filter()
            ->values();

        // Reference queries come back as passages. Only the reference is
        // kept; the reader fetches (and attributes) the chapter itself.
        $passages = collect($response->json('data.passages') ?? [])
            ->map(function ($passage) {
                if (! is_array($passage)) {
                    return null;
                }

                $parsed = preg_match('/^([1-3A-Z]{3})\.(\d+)$/', (string) ($passage['chapterIds'][0] ?? ''), $m)
                    ? [$m[1], (int) $m[2]]
                    : RemoteSearchHit::parseVerseId((string) ($passage['id'] ?? ''));

                return $parsed ? [
                    'reference' => (string) ($passage['reference'] ?? ''),
                    'usfm_book' => $parsed[0],
                    'chapter' => (int) $parsed[1],
                ] : null;
            })
            ->filter()
            ->values();

        $result = new RemoteSearchResult(
            $bibleId,
            $query,
            (int) ($response->json('data.total') ?? $hits->count()),
            $offset,
            $limit,
            $hits,
            $passages,
            $this->fumsToken($response),
            now()->getTimestamp(),
        );

        Cache::put($cacheKey, $result->toCache(), $this->cacheTtl());

        return $result;
    }

    /**
     * The publisher's copyright notice for a Bible, for attributing search
     * results (the search response itself carries none).
     *
     * @throws ApiBibleException
     */
    public function copyright(string $bibleId): string
    {
        $cacheKey = self::CACHE_PREFIX.'copyright:'.$bibleId;

        if ($cached = $this->freshFromCache($cacheKey)) {
            return (string) $cached['copyright'];
        }

        $copyright = trim(strip_tags((string) ($this->bible($bibleId)['copyright'] ?? '')));
        Cache::put($cacheKey, ['copyright' => $copyright, 'fetched_at' => now()->getTimestamp()], $this->cacheTtl());

        return $copyright;
    }

    /** True while a 429 from API.Bible is being honoured. */
    public function isCoolingDown(): bool
    {
        return Cache::has(self::COOLDOWN_KEY);
    }

    public function cacheTtl(): int
    {
        $ttl = (int) config('services.api_bible.cache_ttl', 86400);

        return max(60, min($ttl, self::MAX_CACHE_TTL));
    }

    /**
     * A cached payload, only if it is younger than the CURRENT cache TTL.
     * Cache-store expiry alone is not trusted: if the TTL is lowered, or a
     * store ignores expiry, anything older than the permitted age is evicted
     * instead of being served.
     *
     * @return array<string, mixed>|null
     */
    private function freshFromCache(string $key): ?array
    {
        $cached = Cache::get($key);

        if (! is_array($cached)) {
            return null;
        }

        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt <= 0 || now()->getTimestamp() - $fetchedAt >= $this->cacheTtl()) {
            Cache::forget($key);

            return null;
        }

        $this->usage->record('cache_hit');

        return $cached;
    }

    /**
     * Honour Retry-After (seconds or an HTTP date), bounded to 1 minute–1 hour.
     * No automatic retry is ever made: the cooldown simply pauses calls.
     */
    private function cooldownSeconds(string $retryAfter): int
    {
        $retryAfter = trim($retryAfter);
        $seconds = ctype_digit($retryAfter)
            ? (int) $retryAfter
            : (($at = strtotime($retryAfter)) !== false ? $at - now()->getTimestamp() : 0);

        return $seconds > 0 ? max(60, min($seconds, 3600)) : 60;
    }

    /**
     * The FUMS token exactly as API.Bible issued it — never synthesised.
     */
    private function fumsToken(Response $response): ?string
    {
        $token = $response->json('meta.fumsToken');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, scalar>  $query
     *
     * @throws ApiBibleException
     */
    private function get(string $path, array $query = []): Response
    {
        if (! $this->isConfigured()) {
            throw new ApiBibleException(ApiBibleException::NOT_CONFIGURED, 'API.Bible is not configured.');
        }

        if (Cache::has(self::COOLDOWN_KEY)) {
            $this->usage->record('suppressed');

            throw new ApiBibleException(ApiBibleException::RATE_LIMITED, 'API.Bible rate limit cooldown in effect.');
        }

        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $e) {
            $this->usage->record('network_error');
            // The cURL message embeds the full URL (incl. a user's search
            // query); log the failure without it.
            Log::warning('API.Bible connection failed', ['path' => $path, 'error' => preg_replace('#\s*\(?see https?://\S+\)?|\s*for https?://\S+#', '', $e->getMessage())]);

            throw new ApiBibleException(ApiBibleException::UNAVAILABLE, 'Could not reach API.Bible.');
        }

        if ($response->successful()) {
            $this->usage->record('upstream_success');

            return $response;
        }

        $status = $response->status();
        Log::warning('API.Bible request failed', ['path' => $path, 'status' => $status]);

        if ($status === 429) {
            $this->usage->record('rate_limited');
            Cache::put(self::COOLDOWN_KEY, true, $this->cooldownSeconds($response->header('Retry-After')));

            throw new ApiBibleException(ApiBibleException::RATE_LIMITED);
        }

        $this->usage->record('upstream_error');

        throw new ApiBibleException(match (true) {
            in_array($status, [401, 403], true) => ApiBibleException::UNAUTHORIZED,
            $status === 404 => ApiBibleException::NOT_FOUND,
            default => ApiBibleException::UNAVAILABLE,
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.api_bible.base_url'), '/'))
            ->withHeaders(['api-key' => (string) config('services.api_bible.key')])
            ->acceptJson()
            ->timeout(max(1, (int) config('services.api_bible.timeout', 10)))
            ->connectTimeout(5);
    }

    /**
     * Rebuild verse rows from API.Bible's JSON content tree. Every text node
     * belonging to a verse carries `attrs.verseId` (e.g. "JHN.3.16"); poetry
     * splits a verse over several nodes, so they are joined in order.
     *
     * @param  array<int, mixed>  $nodes
     * @return \Illuminate\Support\Collection<int, RemoteVerse>
     */
    private function extractVerses(array $nodes, string $chapterId): \Illuminate\Support\Collection
    {
        $texts = [];
        $lastVerse = null;

        $walk = function (array $nodes) use (&$walk, &$texts, &$lastVerse, $chapterId): void {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $verseId = $node['attrs']['verseId'] ?? null;
                if (($node['type'] ?? null) === 'text' && is_string($verseId)
                    && str_starts_with($verseId, $chapterId.'.')) {
                    $number = (int) substr($verseId, strlen($chapterId) + 1);
                    if ($number > 0) {
                        $texts[$number][] = (string) ($node['text'] ?? '');
                        $lastVerse = $number;
                    }
                }

                if (! empty($node['items']) && is_array($node['items'])) {
                    $walk($node['items']);
                }

                // Inline runs within a paragraph already carry their own
                // spacing; a paragraph/poetry-line break needs one added.
                if (($node['name'] ?? null) === 'para' && $lastVerse !== null) {
                    $texts[$lastVerse][] = ' ';
                }
            }
        };

        $walk($nodes);
        ksort($texts);

        return collect($texts)
            ->map(fn (array $parts, int $number) => new RemoteVerse(
                $number,
                trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '')
            ))
            ->filter(fn (RemoteVerse $v) => $v->text !== '')
            ->values();
    }
}
