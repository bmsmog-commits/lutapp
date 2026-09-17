<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\Language;
use App\Services\Bible\BibleImportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BibleDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeBook(array $overrides = []): BibleBook
    {
        return BibleBook::create(array_merge([
            'sort_order' => 1,
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'Old Testament',
            'chapters_count' => 50,
        ], $overrides));
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

    public function test_translation_code_must_be_unique(): void
    {
        $this->makeTranslation();

        $this->expectException(QueryException::class);

        $this->makeTranslation();
    }

    public function test_book_sort_order_must_be_unique(): void
    {
        $this->makeBook();

        $this->expectException(QueryException::class);

        $this->makeBook(['name' => 'Exodus', 'abbreviation' => 'Exo']);
    }

    public function test_the_same_verse_reference_is_allowed_in_two_different_translations(): void
    {
        $book = $this->makeBook();
        $kjv = $this->makeTranslation();
        $esv = $this->makeTranslation(['code' => 'ESV', 'name' => 'English Standard Version']);

        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $kjv->id, 'text' => 'In the beginning...']);
        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $esv->id, 'text' => 'In the beginning God created...']);

        $this->assertDatabaseCount('bible_verses', 2);
    }

    public function test_the_same_verse_reference_cannot_be_duplicated_within_one_translation(): void
    {
        $book = $this->makeBook();
        $translation = $this->makeTranslation();

        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'Verse one.']);

        $this->expectException(QueryException::class);

        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'Duplicate verse one.']);
    }

    public function test_bible_translation_links_to_the_language_catalog(): void
    {
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá']);
        $translation = $this->makeTranslation(['code' => 'YOR', 'language' => 'Yoruba', 'language_id' => $language->id]);

        $this->assertTrue($translation->languageRecord->is($language));
    }

    public function test_import_service_refuses_to_import_into_a_non_redistributable_translation(): void
    {
        $this->makeBook();
        $translation = $this->makeTranslation(['code' => 'NIV', 'redistributable' => false, 'public_domain' => false]);

        $this->expectException(RuntimeException::class);

        (new BibleImportService)->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'Copyrighted text.'],
        ]);
    }

    public function test_import_service_imports_valid_rows_into_a_redistributable_translation(): void
    {
        $this->makeBook();
        $translation = $this->makeTranslation();

        $result = (new BibleImportService)->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning...'],
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 2, 'text' => 'And the earth was without form...'],
        ]);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseCount('bible_verses', 2);
    }

    public function test_import_service_skips_invalid_rows_and_reports_errors(): void
    {
        $this->makeBook();
        $translation = $this->makeTranslation();

        $result = (new BibleImportService)->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => ''],
            ['book' => 'Unknown Book', 'chapter' => 1, 'verse' => 1, 'text' => 'Some text.'],
            ['book' => 'Genesis', 'chapter' => 999, 'verse' => 1, 'text' => 'Some text.'],
        ]);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(3, $result['skipped']);
        $this->assertCount(3, $result['errors']);
    }

    public function test_import_service_dry_run_validates_without_persisting(): void
    {
        $this->makeBook();
        $translation = $this->makeTranslation();

        $result = (new BibleImportService)->import($translation, [
            ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning...'],
        ], dryRun: true);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseCount('bible_verses', 0);
    }

    public function test_verify_command_reports_zero_integrity_issues_on_clean_data(): void
    {
        $book = $this->makeBook();
        $translation = $this->makeTranslation();
        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'In the beginning...']);

        $this->artisan('bible:verify')->assertExitCode(0);
    }

    public function test_verify_command_detects_empty_verse_text(): void
    {
        $book = $this->makeBook();
        $translation = $this->makeTranslation();
        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'placeholder']);
        BibleVerse::where('id', BibleVerse::first()->id)->update(['text' => '']);

        $this->artisan('bible:verify')->assertExitCode(1);
    }

    public function test_is_legally_redistributable_reflects_the_redistributable_flag(): void
    {
        $licensed = $this->makeTranslation(['redistributable' => true]);
        $proprietary = $this->makeTranslation(['code' => 'NIV', 'redistributable' => false]);

        $this->assertTrue($licensed->isLegallyRedistributable());
        $this->assertFalse($proprietary->isLegallyRedistributable());
    }
}
