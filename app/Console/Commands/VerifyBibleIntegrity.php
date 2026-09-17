<?php

namespace App\Console\Commands;

use App\Models\BibleTranslation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 data-integrity tooling. Checks structural integrity only — it does not
 * assert a translation is "complete" against the full canonical verse count, since
 * no verified expected-verses-per-chapter dataset is bundled with the app (see
 * Phase 4 report). It reports what the data actually contains, nothing more.
 */
class VerifyBibleIntegrity extends Command
{
    protected $signature = 'bible:verify {translation? : Translation code, e.g. KJV}';

    protected $description = 'Verify Bible data integrity (duplicates, orphans, empty text, book/chapter counts).';

    public function handle(): int
    {
        $translations = $this->argument('translation')
            ? BibleTranslation::where('code', $this->argument('translation'))->get()
            : BibleTranslation::all();

        if ($translations->isEmpty()) {
            $this->error('No matching translation found.');

            return self::FAILURE;
        }

        $hasIssues = false;

        foreach ($translations as $translation) {
            $this->info("Translation: {$translation->code} ({$translation->name})");

            $verseCount = DB::table('bible_verses')->where('translation_id', $translation->id)->count();
            $this->line("  Verses seeded: {$verseCount}");

            $duplicates = DB::table('bible_verses')
                ->select('bible_book_id', 'chapter', 'verse')
                ->where('translation_id', $translation->id)
                ->groupBy('bible_book_id', 'chapter', 'verse')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $emptyText = DB::table('bible_verses')
                ->where('translation_id', $translation->id)
                ->where(function ($query) {
                    $query->whereNull('text')->orWhere('text', '');
                })
                ->count();

            $orphanBooks = DB::table('bible_verses')
                ->leftJoin('bible_books', 'bible_books.id', '=', 'bible_verses.bible_book_id')
                ->where('bible_verses.translation_id', $translation->id)
                ->whereNull('bible_books.id')
                ->count();

            $booksRepresented = DB::table('bible_verses')
                ->where('translation_id', $translation->id)
                ->distinct('bible_book_id')
                ->count('bible_book_id');

            $this->line("  Books with at least one verse: {$booksRepresented} / 66");
            $this->line('  Duplicate (book,chapter,verse) combinations: '.$duplicates);
            $this->line("  Empty verse text rows: {$emptyText}");
            $this->line("  Orphaned book references: {$orphanBooks}");

            if ($duplicates > 0 || $emptyText > 0 || $orphanBooks > 0) {
                $hasIssues = true;
                $this->error('  INTEGRITY ISSUES FOUND for this translation.');
            }

            if ($booksRepresented < 66) {
                $this->warn('  Not a complete Bible: '.(66 - $booksRepresented).' book(s) have zero verses in this translation.');
            }
        }

        $orphanTranslations = DB::table('bible_verses')
            ->leftJoin('bible_translations', 'bible_translations.id', '=', 'bible_verses.translation_id')
            ->whereNull('bible_translations.id')
            ->count();

        if ($orphanTranslations > 0) {
            $hasIssues = true;
            $this->error("Orphaned translation references across all verses: {$orphanTranslations}");
        }

        return $hasIssues ? self::FAILURE : self::SUCCESS;
    }
}
