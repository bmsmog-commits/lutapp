<?php

namespace App\Console\Commands;

use App\Models\BibleReadingHistory;
use Illuminate\Console\Command;

/**
 * Phase 27.6 — deletes Bible reading-history rows past their 24-hour window.
 *
 * Scope is deliberately narrow: this command touches ONLY
 * `bible_reading_histories`. It never deletes bookmarks, highlights, notes, or
 * any row of bible_verses / bible_books.
 */
class PruneBibleReadingHistory extends Command
{
    protected $signature = 'bible:prune-reading-history {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete Bible reading-history entries older than the 24-hour retention window';

    public function handle(): int
    {
        $expired = BibleReadingHistory::query()->expired();

        if ($this->option('dry-run')) {
            $this->info("{$expired->count()} expired reading-history entries would be deleted.");

            return self::SUCCESS;
        }

        $deleted = $expired->delete();

        $this->info("Pruned {$deleted} expired Bible reading-history entries.");

        return self::SUCCESS;
    }
}
