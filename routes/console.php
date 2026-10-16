<?php

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Phase 27.6 — the application's first scheduled task.
 *
 * IMPORTANT: registering it here does NOT make it run. A production host must
 * add the Laravel scheduler cron entry manually:
 *
 *     * * * * * cd /path/to/Lutapp && php artisan schedule:run >> /dev/null 2>&1
 *
 * Without that cron entry (which is the case in this local dev environment)
 * nothing is pruned automatically. The 24-hour cutoff is still enforced at read
 * time by BibleReadingHistory::scopeNotExpired(), so expired entries are never
 * surfaced to a user even when the cleanup has not run.
 */
Schedule::command('bible:prune-reading-history')->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bible:import {path} {--translation=KJV}', function (string $path): int {
    if (! file_exists($path)) {
        $this->error("File not found: {$path}");

        return 1;
    }

    $verses = json_decode(file_get_contents($path), true);

    if (! is_array($verses)) {
        $this->error('Bible import file must be a JSON array.');

        return 1;
    }

    $translation = strtoupper((string) $this->option('translation'));
    $translationId = BibleTranslation::where('code', $translation)->value('id');

    if (! $translationId) {
        $this->error("Unknown translation code: {$translation}");

        return 1;
    }

    $books = BibleBook::all()
        ->keyBy(fn (BibleBook $book) => strtolower($book->name))
        ->merge(BibleBook::all()->keyBy(fn (BibleBook $book) => strtolower($book->abbreviation)));

    $count = 0;

    foreach ($verses as $item) {
        $bookName = strtolower((string) ($item['book'] ?? ''));
        $book = $books->get($bookName);

        if (! $book) {
            $this->warn("Skipped unknown book: {$bookName}");
            continue;
        }

        BibleVerse::updateOrCreate(
            [
                'bible_book_id' => $book->id,
                'chapter' => (int) $item['chapter'],
                'verse' => (int) $item['verse'],
                'translation_id' => $translationId,
            ],
            ['text' => (string) $item['text']]
        );

        $count++;
    }

    $this->info("Imported {$count} {$translation} verses.");

    return 0;
})->purpose('Import public-domain Bible verses from a JSON file');
