<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BibleReadingHistoryRetentionTest extends TestCase
{
    use RefreshDatabase;

    private BibleTranslation $translation;

    private BibleBook $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translation = BibleTranslation::create([
            'code' => 'KJV',
            'name' => 'King James Version',
            'language' => 'English',
            'license' => 'Public Domain',
            'license_type' => 'public_domain',
            'public_domain' => true,
            'redistributable' => true,
            'is_active' => true,
        ]);

        $this->book = BibleBook::create([
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'old',
            'chapters_count' => 50,
            'sort_order' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reading_a_chapter_stamps_an_expiry_24_hours_later(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = User::factory()->create();

        BibleVerse::create([
            'bible_book_id' => $this->book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $this->translation->id,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);

        $this->actingAs($user)->get(route('bible.index', [
            'book' => $this->book->id,
            'chapter' => 1,
            'translation_id' => $this->translation->id,
        ]))->assertOk();

        $entry = BibleReadingHistory::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('2026-09-21 09:00:00', $entry->last_read_at->toDateTimeString());
        $this->assertSame('2026-09-22 09:00:00', $entry->expires_at->toDateTimeString());
        $this->assertSame(24, BibleReadingHistory::RETENTION_HOURS);
    }

    public function test_entries_older_than_24_hours_are_not_surfaced(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = User::factory()->create();
        $entry = BibleReadingHistory::record($user->id, $this->book->id, 3, $this->translation->id);

        $this->assertFalse($entry->isExpired());
        $this->assertSame(1, BibleReadingHistory::query()->notExpired()->count());

        // 23 hours later: still inside the window.
        Carbon::setTestNow('2026-09-22 08:00:00');
        $this->assertSame(1, BibleReadingHistory::query()->notExpired()->count());
        $this->assertSame(0, BibleReadingHistory::query()->expired()->count());

        // 25 hours later: outside it.
        Carbon::setTestNow('2026-09-22 10:00:00');
        $this->assertSame(0, BibleReadingHistory::query()->notExpired()->count());
        $this->assertSame(1, BibleReadingHistory::query()->expired()->count());
        $this->assertTrue($entry->fresh()->isExpired());

        $this->actingAs($user)->get(route('bible.reading-history'))
            ->assertOk()
            ->assertDontSee('Genesis 3');
    }

    public function test_prune_command_deletes_only_expired_reading_history(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = User::factory()->create();
        BibleReadingHistory::record($user->id, $this->book->id, 1, $this->translation->id);

        $bookmark = BibleBookmark::create([
            'user_id' => $user->id,
            'bible_book_id' => $this->book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $this->translation->id,
        ]);

        $highlight = BibleHighlight::create([
            'user_id' => $user->id,
            'bible_book_id' => $this->book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $this->translation->id,
            'color' => 'yellow',
        ]);

        Carbon::setTestNow('2026-09-23 09:00:00');

        // Fresh row that must survive the prune.
        BibleReadingHistory::record($user->id, $this->book->id, 9, $this->translation->id);

        $this->artisan('bible:prune-reading-history')->assertExitCode(0);

        $this->assertSame(1, BibleReadingHistory::count());
        $this->assertSame(9, (int) BibleReadingHistory::first()->chapter);

        // Permanent user data is untouched.
        $this->assertModelExists($bookmark);
        $this->assertModelExists($highlight);
    }

    public function test_bookmarks_and_highlights_have_no_expiry_concept(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('bible_bookmarks', 'expires_at'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('bible_highlights', 'expires_at'));
    }

    public function test_bible_index_shows_continue_reading_only_for_live_entries(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');

        $user = User::factory()->create();
        BibleReadingHistory::record($user->id, $this->book->id, 7, $this->translation->id);

        $this->actingAs($user)->get(route('bible.index'))
            ->assertOk()
            ->assertSee('continue-reading', false)
            ->assertSee('Genesis 7');

        Carbon::setTestNow('2026-09-23 09:00:00');

        $this->actingAs($user)->get(route('bible.index'))
            ->assertOk()
            ->assertDontSee('Genesis 7');
    }
}
