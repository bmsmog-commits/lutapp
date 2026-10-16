<?php

namespace App\Console\Commands;

use App\Services\Bible\ApiBible\ApiBibleUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Phase 27.10 — show this application's estimated API.Bible usage. Makes no
 * API call.
 */
class ApiBibleUsageReport extends Command
{
    protected $signature = 'bible:api-usage {--month= : Calendar month (UTC) as YYYY-MM; defaults to the current month}';

    protected $description = 'Show application-side estimates of API.Bible usage for a month (the API.Bible dashboard is authoritative).';

    public function handle(ApiBibleUsage $usage): int
    {
        $month = null;
        if ($option = $this->option('month')) {
            if (! preg_match('/^\d{4}-\d{2}$/', $option)) {
                $this->error('Use --month=YYYY-MM.');

                return self::FAILURE;
            }
            $month = Carbon::createFromFormat('Y-m-d', $option.'-01', 'UTC');
        }

        $s = $usage->snapshot($month);

        $this->info("API.Bible usage estimate for {$s['month']} (UTC calendar month)");
        $this->table(['Metric', 'Count', 'Upstream call?'], collect($s['counts'])->map(fn (int $count, string $metric) => [
            $metric,
            $count,
            in_array($metric, ApiBibleUsage::UPSTREAM_METRICS, true) ? 'yes' : ($metric === 'network_error' ? 'maybe' : 'no'),
        ])->values()->all());

        $this->line("Estimated upstream calls: {$s['upstream_calls']} of {$s['monthly_limit']} ({$s['percent_of_limit']}%), warning at {$s['warning_at']}.");
        $this->warn('These are estimates of requests sent by this application only. The API.Bible dashboard (https://api.bible) is the authoritative usage and billing record.');

        return self::SUCCESS;
    }
}
