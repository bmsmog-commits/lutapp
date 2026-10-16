<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\User;
use App\Services\Bible\ApiBible\ApiBibleClient;
use App\Services\Bible\ApiBible\ApiBibleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 27.8 — API.Bible integration. Every HTTP call is faked: the suite never
 * needs a real API key and never consumes API.Bible quota.
 */
class ApiBibleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const WEB_ID = '9879dbb7cfe39e4d-01';
    private const NLT_ID = 'd6e14a625393b4da-01';
    private const BASE = 'https://rest.api.bible/v1';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();

        config([
            'services.api_bible.key' => 'test-key-not-real',
            'services.api_bible.base_url' => self::BASE,
            'services.api_bible.cache_ttl' => 86400,
            'services.api_bible.allow_copyrighted' => false,
        ]);
    }

    private function john(): BibleBook
    {
        return BibleBook::create([
            'sort_order' => 43,
            'name' => 'John',
            'abbreviation' => 'Joh',
            'testament' => 'New',
            'chapters_count' => 21,
        ]);
    }

    private function kjv(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'KJV', 'name' => 'King James Version', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true,
            'is_active' => true,
        ]);
    }

    private function web(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'WEB', 'name' => 'World English Bible', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true,
            'api_bible_id' => self::WEB_ID, 'is_active' => true,
        ]);
    }

    private function nlt(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'NLT', 'name' => 'New Living Translation', 'language' => 'English',
            'license' => 'Proprietary', 'public_domain' => false, 'redistributable' => false,
            'license_type' => 'licensed_remote', 'availability_status' => 'license_required',
            'api_bible_id' => self::NLT_ID, 'is_active' => true,
        ]);
    }

    /** A chapter payload shaped like API.Bible's content-type=json response. */
    private function chapterPayload(string $bibleId, string $copyright = 'PUBLIC DOMAIN'): array
    {
        $text = fn (string $verseId, string $text) => [
            'text' => $text, 'type' => 'text', 'attrs' => ['verseId' => $verseId, 'verseOrgIds' => [$verseId]],
        ];

        return [
            'data' => [
                'id' => 'JHN.3',
                'bibleId' => $bibleId,
                'reference' => 'John 3',
                'copyright' => $copyright,
                'content' => [
                    ['name' => 'para', 'type' => 'tag', 'attrs' => ['style' => 'p'], 'items' => [
                        ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '16', 'style' => 'v'], 'items' => [['text' => '16', 'type' => 'text']]],
                        $text('JHN.3.16', 'Mock remote verse sixteen, '),
                        ['name' => 'char', 'type' => 'tag', 'attrs' => ['style' => 'wj'], 'items' => [$text('JHN.3.16', 'with styled words.')]],
                    ]],
                    // A poetry-style continuation of verse 17 across two paragraphs.
                    ['name' => 'para', 'type' => 'tag', 'attrs' => ['style' => 'q1'], 'items' => [
                        $text('JHN.3.17', 'Mock remote verse seventeen,'),
                    ]],
                    ['name' => 'para', 'type' => 'tag', 'attrs' => ['style' => 'q2'], 'items' => [
                        $text('JHN.3.17', 'continued on a new line.'),
                    ]],
                ],
            ],
            'meta' => ['fumsToken' => 'fums-token-123'],
        ];
    }

    private function fakeChapter(string $bibleId = self::WEB_ID, string $copyright = 'PUBLIC DOMAIN'): void
    {
        Http::fake([
            self::BASE.'/bibles/'.$bibleId.'/chapters/JHN.3*' => Http::response($this->chapterPayload($bibleId, $copyright)),
        ]);
    }

    public function test_client_fetches_a_chapter_with_the_api_key_and_fums_v3(): void
    {
        $this->fakeChapter();

        $chapter = app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);

        $this->assertSame('John 3', $chapter->reference);
        $this->assertSame('PUBLIC DOMAIN', $chapter->copyright);
        $this->assertSame('fums-token-123', $chapter->fumsToken);
        $this->assertSame([16, 17], $chapter->verses->pluck('verse')->all());
        $this->assertSame('Mock remote verse sixteen, with styled words.', $chapter->verses[0]->text);
        $this->assertSame('Mock remote verse seventeen, continued on a new line.', $chapter->verses[1]->text);

        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'test-key-not-real')
            && str_contains($r->url(), '/bibles/'.self::WEB_ID.'/chapters/JHN.3')
            && $r['fums-version'] === '3'
            && $r['content-type'] === 'json');
    }

    public function test_chapters_are_cached_so_repeat_reads_do_not_consume_api_calls(): void
    {
        $this->fakeChapter();
        $client = app(ApiBibleClient::class);

        $client->chapter(self::WEB_ID, 'JHN', 3);
        $again = $client->chapter(self::WEB_ID, 'JHN', 3);

        Http::assertSentCount(1);
        $this->assertSame(2, $again->verses->count());
        $this->assertSame('fums-token-123', $again->fumsToken);
    }

    public function test_cache_ttl_never_exceeds_api_bible_fourteen_day_limit(): void
    {
        config(['services.api_bible.cache_ttl' => 60 * 60 * 24 * 365]);

        $this->assertSame(14 * 24 * 60 * 60, app(ApiBibleClient::class)->cacheTtl());
    }

    public function test_reader_renders_remote_text_with_attribution_and_fums_without_storing_it(): void
    {
        $this->fakeChapter();
        $user = User::factory()->create();
        $book = $this->john();
        $this->kjv();
        $web = $this->web();

        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]))
            ->assertOk()
            ->assertSee('Mock remote verse sixteen, with styled words.')
            ->assertSee('data-testid="api-bible-attribution"', false)
            ->assertSee('PUBLIC DOMAIN')
            ->assertSee('https://pkg.api.bible/fumsV3.min.js', false)
            ->assertSee('fums-token-123')
            ->assertDontSee('data-testid="bible-translation-unavailable"', false);

        // Remote text is displayed, never persisted.
        $this->assertSame(0, BibleVerse::count());

        // The 24-hour reading history still records the remote chapter.
        $history = BibleReadingHistory::where('user_id', $user->id)->sole();
        $this->assertSame($web->id, (int) $history->translation_id);
        $this->assertNotNull($history->expires_at);
    }

    public function test_study_workspace_renders_remote_text_beside_the_notes_editor(): void
    {
        $this->fakeChapter();
        $user = User::factory()->create();
        $book = $this->john();
        $web = $this->web();

        $this->actingAs($user)
            ->get(route('bible.study', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]))
            ->assertOk()
            ->assertSee('Mock remote verse seventeen, continued on a new line.')
            ->assertSee('John 3:16 (WEB)')
            ->assertSee('name="bible_translation_id" value="'.$web->id.'"', false)
            ->assertSee('data-testid="api-bible-attribution"', false);
    }

    public function test_bookmarks_and_highlights_work_on_remote_translations(): void
    {
        $user = User::factory()->create();
        $book = $this->john();
        $web = $this->web();
        $ref = ['bible_book_id' => $book->id, 'chapter' => 3, 'verse' => 16, 'translation_id' => $web->id];

        $this->actingAs($user)->post(route('bible.bookmark'), $ref)->assertRedirect();
        $this->actingAs($user)->post(route('bible.highlight'), $ref + ['color' => 'yellow'])->assertRedirect();

        $this->assertDatabaseHas('bible_bookmarks', ['user_id' => $user->id] + $ref);
        $this->assertDatabaseHas('bible_highlights', ['user_id' => $user->id, 'color' => 'yellow'] + $ref);

        $this->fakeChapter();
        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]))
            ->assertOk()
            ->assertSee('hl-yellow')
            ->assertSee('is-bookmarked');

        $this->assertSame(1, BibleBookmark::count());
        $this->assertSame(1, BibleHighlight::count());
        Http::assertSentCount(1);
    }

    public function test_local_text_always_wins_and_never_triggers_a_remote_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $book = $this->john();
        $kjv = $this->kjv();
        // Even a translation linked to API.Bible is served locally when local rows exist.
        $kjv->update(['api_bible_id' => 'de4e12af7f28f599-01']);
        BibleVerse::create([
            'bible_book_id' => $book->id, 'translation_id' => $kjv->id,
            'chapter' => 3, 'verse' => 16, 'text' => 'Local KJV verse text.',
        ]);

        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $kjv->id]))
            ->assertOk()
            ->assertSee('Local KJV verse text.')
            ->assertDontSee('data-testid="api-bible-attribution"', false);

        Http::assertNothingSent();
    }

    public function test_missing_configuration_makes_no_request_and_shows_the_honest_empty_state(): void
    {
        config(['services.api_bible.key' => null]);
        Http::fake();
        $user = User::factory()->create();
        $book = $this->john();
        $web = $this->web();

        $this->assertNotSame('remote', $web->availabilityState(false));

        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]))
            ->assertOk()
            ->assertSee('data-testid="bible-translation-unavailable"', false)
            ->assertSee(__('messages.translation_text_not_imported_title'));

        Http::assertNothingSent();

        $this->expectException(ApiBibleException::class);
        app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);
    }

    public function test_copyrighted_translations_stay_licence_required_until_explicitly_allowed(): void
    {
        $notice = 'Holy Bible, New Living Translation, copyright © 1996, 2004, 2015 by Tyndale House Foundation.';
        $this->fakeChapter(self::NLT_ID, $notice);
        $user = User::factory()->create();
        $book = $this->john();
        $nlt = $this->nlt();

        $this->assertSame('license_required', $nlt->availabilityState(false));
        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $nlt->id]))
            ->assertOk()
            ->assertSee(__('messages.translation_text_licensed_note'));
        Http::assertNothingSent();

        config(['services.api_bible.allow_copyrighted' => true]);

        $this->assertSame('remote', $nlt->availabilityState(false));
        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $nlt->id]))
            ->assertOk()
            ->assertSee('Mock remote verse sixteen')
            ->assertSee($notice);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function failureProvider(): array
    {
        return [
            'unauthorized' => [401, ApiBibleException::UNAUTHORIZED],
            'forbidden' => [403, ApiBibleException::UNAUTHORIZED],
            'not found' => [404, ApiBibleException::NOT_FOUND],
            'server error' => [503, ApiBibleException::UNAVAILABLE],
            'empty content' => ['empty', ApiBibleException::UNAVAILABLE],
            'network failure' => ['connection', ApiBibleException::UNAVAILABLE],
        ];
    }

    #[DataProvider('failureProvider')]
    public function test_api_failures_render_a_graceful_explanation(mixed $failure, string $reason): void
    {
        Http::fake([
            self::BASE.'/*' => match ($failure) {
                'connection' => fn () => throw new ConnectionException('Connection timed out'),
                'empty' => Http::response(['data' => ['reference' => 'John 3', 'content' => []], 'meta' => []]),
                default => Http::response(['error' => 'x'], $failure),
            },
        ]);

        $user = User::factory()->create();
        $book = $this->john();
        $kjv = $this->kjv();
        BibleVerse::create([
            'bible_book_id' => $book->id, 'translation_id' => $kjv->id,
            'chapter' => 3, 'verse' => 16, 'text' => 'Local KJV verse text.',
        ]);
        $web = $this->web();

        $this->actingAs($user)
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]))
            ->assertOk()
            ->assertSee('data-remote-error="'.$reason.'"', false)
            ->assertSee(__('messages.api_bible_error_title'))
            ->assertSee(__('messages.api_bible_error_'.$reason))
            // The local KJV fallback is still offered.
            ->assertSee(__('messages.translation_read_in', ['translation' => 'KJV']));

        // Failures are never cached as text.
        $this->assertNull(Cache::get('api_bible:v2:chapter:'.self::WEB_ID.':JHN.3'));
        $this->assertSame(1, BibleVerse::count());
    }

    public function test_rate_limiting_sets_a_cooldown_that_stops_further_calls(): void
    {
        Http::fake([
            self::BASE.'/*' => Http::response(['error' => 'Too Many Requests'], 429, ['Retry-After' => '120']),
        ]);
        $user = User::factory()->create();
        $book = $this->john();
        $web = $this->web();
        $url = route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $web->id]);

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee(__('messages.api_bible_error_rate_limited'));

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee(__('messages.api_bible_error_rate_limited'));

        Http::assertSentCount(1);
    }

    public function test_reader_marks_remote_translations_as_readable_in_the_selector(): void
    {
        $user = User::factory()->create();
        $this->kjv();
        $this->web();

        $this->actingAs($user)
            ->get(route('bible.index'))
            ->assertOk()
            ->assertSee('data-state="remote"', false)
            ->assertSee(__('messages.translation_state_remote'));
    }

    public function test_catalog_command_lists_the_bibles_this_key_can_access(): void
    {
        Http::fake([
            self::BASE.'/bibles' => Http::response(['data' => [
                ['id' => self::WEB_ID, 'abbreviationLocal' => 'WEB', 'name' => 'World English Bible', 'language' => ['id' => 'eng']],
                ['id' => 'abc-01', 'abbreviationLocal' => 'XYZ', 'name' => 'Other Bible', 'language' => ['id' => 'deu']],
            ]]),
        ]);

        $this->artisan('bible:api-bible-catalog', ['--language' => 'eng'])
            ->expectsOutputToContain('2 Bibles available to this key')
            ->assertSuccessful();
    }

    public function test_catalog_command_fails_cleanly_without_configuration(): void
    {
        config(['services.api_bible.key' => '']);
        Http::fake();

        $this->artisan('bible:api-bible-catalog')->assertFailed();
        Http::assertNothingSent();
    }
}
