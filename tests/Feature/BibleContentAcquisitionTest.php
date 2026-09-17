<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Services\Bible\BibleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests exercise BibleImportService against small, inline representative
 * samples rather than fetching the live network dataset — the actual full KJV
 * import was run and verified manually against the real source (see Phase 5
 * report), but the automated suite must not depend on network access.
 */
class BibleContentAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooks(): void
    {
        BibleBook::create(['sort_order' => 1, 'name' => 'Genesis', 'abbreviation' => 'Gen', 'testament' => 'Old Testament', 'chapters_count' => 50]);
        BibleBook::create(['sort_order' => 40, 'name' => 'Matthew', 'abbreviation' => 'Matt', 'testament' => 'New Testament', 'chapters_count' => 28]);
    }

    private function makeTranslation(array $overrides = []): BibleTranslation
    {
        return BibleTranslation::create(array_merge([
            'code' => 'KJV',
            'name' => 'King James Version',
            'language' => 'English',
            'public_domain' => true,
            'redistributable' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_import_produces_exact_verse_count_for_a_multi_book_sample(): void
    {
        $this->makeBooks();
        $translation = $this->makeTranslation();

        $rows = [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning God created the heaven and the earth.'],
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 2, 'text' => 'And the earth was without form, and void...'],
            ['book' => 'Matthew', 'chapter' => 1, 'verse' => 1, 'text' => 'The book of the generation of Jesus Christ...'],
        ];

        $result = (new BibleImportService)->import($translation, $rows);

        $this->assertSame(3, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('bible_verses', 3);
    }

    public function test_re_running_the_same_import_is_idempotent(): void
    {
        $this->makeBooks();
        $translation = $this->makeTranslation();

        $rows = [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning God created the heaven and the earth.'],
        ];

        $importer = new BibleImportService;
        $importer->import($translation, $rows);
        $result = $importer->import($translation, $rows);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('bible_verses', 1);
    }

    public function test_re_importing_with_corrected_text_updates_rather_than_duplicates(): void
    {
        $this->makeBooks();
        $translation = $this->makeTranslation();
        $importer = new BibleImportService;

        $importer->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'Typo version.'],
        ]);
        $importer->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'Corrected version.'],
        ]);

        $this->assertDatabaseCount('bible_verses', 1);
        $this->assertSame('Corrected version.', BibleVerse::first()->text);
    }

    public function test_importing_the_same_reference_into_two_translations_keeps_them_isolated(): void
    {
        $this->makeBooks();
        $kjv = $this->makeTranslation();
        $esv = $this->makeTranslation(['code' => 'ESV', 'name' => 'English Standard Version']);

        $importer = new BibleImportService;
        $importer->import($kjv, [['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'KJV text.']]);
        $importer->import($esv, [['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'ESV text.']]);

        $this->assertDatabaseCount('bible_verses', 2);
        $this->assertSame(1, BibleVerse::where('translation_id', $kjv->id)->count());
        $this->assertSame(1, BibleVerse::where('translation_id', $esv->id)->count());
    }

    public function test_import_does_not_disturb_existing_bookmarks_highlights_or_reading_history(): void
    {
        $this->makeBooks();
        $translation = $this->makeTranslation();
        $book = BibleBook::where('name', 'Genesis')->first();
        $user = \App\Models\User::factory()->create();

        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'Original.']);

        $bookmark = \App\Models\BibleBookmark::create([
            'user_id' => $user->id, 'bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id,
        ]);

        (new BibleImportService)->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'Re-imported text.'],
        ]);

        $this->assertDatabaseHas('bible_bookmarks', ['id' => $bookmark->id]);
    }
}
