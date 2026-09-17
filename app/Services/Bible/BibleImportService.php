<?php

namespace App\Services\Bible;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Foundation for importing verse text into an existing, licensed translation.
 *
 * This service intentionally ships with no bundled Bible text — callers must supply
 * their own legally-sourced dataset (public-domain file, licensed API response, etc.).
 * It refuses to import into a translation that has not been marked redistributable,
 * so a licensing mistake in BibleTranslationSeeder cannot silently ship copyrighted
 * text. See BibleTranslation::isLegallyRedistributable().
 */
class BibleImportService
{
    /**
     * @param  iterable<array{book: string, chapter: int, verse: int, text: string}>  $rows
     *         'book' may be a book name or abbreviation.
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(BibleTranslation $translation, iterable $rows, bool $dryRun = false): array
    {
        if (! $translation->isLegallyRedistributable()) {
            throw new RuntimeException(
                "Refusing to import verses for translation [{$translation->code}]: it is not marked redistributable. ".
                'Verify licensing and set redistributable = true before importing.'
            );
        }

        $books = BibleBook::all()->keyBy(fn (BibleBook $book) => strtolower($book->name))
            ->union(BibleBook::all()->keyBy(fn (BibleBook $book) => strtolower($book->abbreviation)));

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $apply = function () use ($rows, $books, $translation, &$imported, &$skipped, &$errors) {
            foreach ($rows as $index => $row) {
                $error = $this->validateRow($row);

                if ($error !== null) {
                    $errors[] = "Row {$index}: {$error}";
                    $skipped++;

                    continue;
                }

                $book = $books->get(strtolower($row['book']));

                if (! $book) {
                    $errors[] = "Row {$index}: unknown book '{$row['book']}'.";
                    $skipped++;

                    continue;
                }

                if ($row['chapter'] > $book->chapters_count) {
                    $errors[] = "Row {$index}: chapter {$row['chapter']} exceeds {$book->name}'s known chapter count ({$book->chapters_count}).";
                    $skipped++;

                    continue;
                }

                BibleVerse::updateOrCreate(
                    [
                        'bible_book_id' => $book->id,
                        'chapter' => $row['chapter'],
                        'verse' => $row['verse'],
                        'translation_id' => $translation->id,
                    ],
                    ['text' => $row['text']]
                );

                $imported++;
            }
        };

        if ($dryRun) {
            // Run inside a transaction we always roll back, so validation/upsert
            // logic is exercised without persisting anything.
            DB::beginTransaction();
            $apply();
            DB::rollBack();
        } else {
            DB::transaction($apply);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function validateRow(array $row): ?string
    {
        if (empty($row['book'])) {
            return 'missing book.';
        }

        if (! isset($row['chapter']) || (int) $row['chapter'] < 1) {
            return 'invalid chapter number.';
        }

        if (! isset($row['verse']) || (int) $row['verse'] < 1) {
            return 'invalid verse number.';
        }

        if (empty(trim((string) ($row['text'] ?? '')))) {
            return 'empty verse text.';
        }

        return null;
    }
}
