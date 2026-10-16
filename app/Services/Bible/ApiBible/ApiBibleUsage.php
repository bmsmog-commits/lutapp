<?php

namespace App\Services\Bible\ApiBible;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 27.10 — application-side API.Bible usage counters.
 *
 * These are ESTIMATES of requests THIS application sent, kept per calendar
 * month (UTC) in the application cache. They are not API.Bible's billing
 * record: other apps or scripts sharing the key, the provider's own billing
 * cycle, cache flushes and non-atomic cache stores (e.g. `file`) all make
 * them diverge. The API.Bible dashboard is the authoritative source. Nothing
 * here blocks requests — it only warns.
 *
 * Counted per month:
 *  - upstream_success  2xx responses from API.Bible
 *  - upstream_error    non-2xx, non-429 responses (they still reached API.Bible)
 *  - rate_limited      429 responses
 *  - network_error     connection failures (may or may not have reached it)
 *  - cache_hit         served from cache — NOT an upstream call
 *  - suppressed        skipped during a 429 cooldown — NOT an upstream call
 */
class ApiBibleUsage
{
    public const METRICS = ['upstream_success', 'upstream_error', 'rate_limited', 'network_error', 'cache_hit', 'suppressed'];

    /** Metrics that represent a request API.Bible received (and may bill). */
    public const UPSTREAM_METRICS = ['upstream_success', 'upstream_error', 'rate_limited'];

    private const TTL = 40 * 24 * 60 * 60;

    public function record(string $metric): void
    {
        if (! in_array($metric, self::METRICS, true)) {
            return;
        }

        $key = $this->key($metric);

        try {
            // Some stores (database) return false when incrementing a missing
            // key, so seed it first; add() is a no-op if it already exists.
            Cache::add($key, 0, self::TTL);
            Cache::increment($key);
        } catch (\Throwable $e) {
            // Usage accounting must never break Scripture reading.
            Log::debug('API.Bible usage counter could not be updated', ['metric' => $metric, 'error' => $e->getMessage()]);

            return;
        }

        if (in_array($metric, self::UPSTREAM_METRICS, true)) {
            $this->warnIfNearLimit();
        }
    }

    /**
     * @return array{month: string, counts: array<string, int>, upstream_calls: int, monthly_limit: int, warning_at: int, percent_of_limit: float}
     */
    public function snapshot(?Carbon $month = null): array
    {
        $month ??= now('UTC');
        $counts = [];
        foreach (self::METRICS as $metric) {
            $counts[$metric] = (int) Cache::get($this->key($metric, $month), 0);
        }

        $upstream = array_sum(array_intersect_key($counts, array_flip(self::UPSTREAM_METRICS)));
        $limit = $this->monthlyLimit();

        return [
            'month' => $month->format('Y-m'),
            'counts' => $counts,
            'upstream_calls' => $upstream,
            'monthly_limit' => $limit,
            'warning_at' => $this->warningAt(),
            'percent_of_limit' => $limit > 0 ? round($upstream / $limit * 100, 1) : 0.0,
        ];
    }

    public function monthlyLimit(): int
    {
        return max(0, (int) config('services.api_bible.monthly_limit', 5000));
    }

    public function warningAt(): int
    {
        $threshold = min(1.0, max(0.0, (float) config('services.api_bible.warning_threshold', 0.8)));

        return (int) floor($this->monthlyLimit() * $threshold);
    }

    private function warnIfNearLimit(): void
    {
        $limit = $this->monthlyLimit();
        if ($limit === 0) {
            return;
        }

        $snapshot = $this->snapshot();
        $upstream = $snapshot['upstream_calls'];

        foreach ([['limit', $limit, 'error'], ['warning', $this->warningAt(), 'warning']] as [$name, $at, $level]) {
            // Cache::add is the once-per-month latch for each alert.
            if ($upstream >= $at && Cache::add($this->key('alert_'.$name), true, self::TTL)) {
                Log::log($level, "API.Bible estimated monthly usage reached the {$name} level", [
                    'month' => $snapshot['month'],
                    'estimated_upstream_calls' => $upstream,
                    'alert_at' => $at,
                    'monthly_limit' => $limit,
                    'note' => 'Application-side estimate; confirm in the API.Bible dashboard.',
                ]);

                return;
            }
        }
    }

    private function key(string $metric, ?Carbon $month = null): string
    {
        return 'api_bible:usage:'.($month ?? now('UTC'))->format('Y-m').':'.$metric;
    }
}
