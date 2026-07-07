<?php

use App\Models\BibleBook;
use App\Models\BibleVerse;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
                'translation' => $translation,
            ],
            ['text' => (string) $item['text']]
        );

        $count++;
    }

    $this->info("Imported {$count} {$translation} verses.");

    return 0;
})->purpose('Import public-domain Bible verses from a JSON file');
