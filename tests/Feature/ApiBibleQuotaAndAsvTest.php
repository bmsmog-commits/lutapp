<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\User;
use App\Services\Bible\ApiBible\ApiBibleClient;
use App\Services\Bible\ApiBible\ApiBibleException;
use App\Services\Bible\ApiBible\ApiBibleUsage;
use Database\Seeders\BibleTranslationSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 27.10 — ASV verification gate, API.Bible usage accounting, 429
 * handling, seeder idempotency and migration portability. No live API calls.
 */
class ApiBibleQuotaAndAsvTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://rest.api.bible/v1';
    private const ASV_ID = '06125adad2d5898a-01';
    private const WEB_ID = '9879dbb7cfe39e4d-01';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.api_bible.key' => 'test-key-not-real',
            'services.api_bible.base_url' => self::BASE,
            'services.api_bible.cache_ttl' => 86400,
            'services.api_bible.allow_copyrighted' => false,
            'services.api_bible.pending_verification' => ['ASV'],
            'services.api_bible.monthly_limit' => 5000,
            'services.api_bible.warning_threshold' => 0.8,
        ]);
    }

    private function chapter(string $bibleId = self::WEB_ID): array
    {
        return [
            'data' => [
                'id' => 'JHN.3', 'bibleId' => $bibleId, 'reference' => 'John 3', 'copyright' => 'PUBLIC DOMAIN',
                'content' => [[
                    'name' => 'para', 'type' => 'tag', 'attrs' => ['style' => 'p'],
                    'items' => [['text' => 'For God so loved the world, that he gave his only begotten Son.', 'type' => 'text', 'attrs' => ['verseId' => 'JHN.3.16']]],
                ]],
            ],
            'meta' => ['fumsToken' => 'fums-'.str_repeat('x', 40)],
        ];
    }

    private function asv(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'ASV', 'name' => 'American Standard Version (1901)', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true,
            'api_bible_id' => self::ASV_ID, 'is_active' => true,
        ]);
    }

    private function john(): BibleBook
    {
        return BibleBook::create(['sort_order' => 43, 'name' => 'John', 'abbreviation' => 'Joh', 'testament' => 'New', 'chapters_count' => 21]);
    }

    private function usage(): array
    {
        return app(ApiBibleUsage::class)->snapshot();
    }

    // ---------------------------------------------------------------
    // ASV gate
    // ---------------------------------------------------------------

    public function test_asv_is_held_for_licence_verification_and_makes_no_api_call(): void
    {
        Http::fake();
        $asv = $this->asv();
        $book = $this->john();

        $this->assertTrue($asv->isPendingVerification());
        $this->assertFalse($asv->canBeServedRemotely());
        $this->assertSame('license_required', $asv->availabilityState());

        $this->actingAs(User::factory()->create())
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $asv->id]))
            ->assertOk()
            ->assertSee('data-state="license_required"', false)
            ->assertSee(__('messages.translation_state_license_required'))
            ->assertSee('data-testid="bible-translation-unavailable"', false)
            ->assertDontSee('he gave his only begotten Son');

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'loved', 'translation_id' => $asv->id]))
            ->assertOk()
            ->assertSee('data-testid="bible-search-unsupported"', false);

        Http::assertNothingSent();
    }

    public function test_clearing_the_pending_list_serves_asv_remotely_with_attribution(): void
    {
        config(['services.api_bible.pending_verification' => []]);
        Http::fake([self::BASE.'/bibles/'.self::ASV_ID.'/chapters/*' => Http::response($this->chapter(self::ASV_ID))]);
        $asv = $this->asv();
        $book = $this->john();

        $this->assertSame('remote', $asv->availabilityState());

        $this->actingAs(User::factory()->create())
            ->get(route('bible.index', ['book' => $book->id, 'chapter' => 3, 'translation_id' => $asv->id]))
            ->assertOk()
            ->assertSee('he gave his only begotten Son')
            ->assertSee('data-testid="api-bible-attribution"', false)
            ->assertSee('PUBLIC DOMAIN')
            ->assertSee('data-fums-source="live"', false);

        $this->assertSame(0, BibleVerse::count());
    }

    public function test_the_pending_gate_never_applies_to_translations_without_an_api_bible_link(): void
    {
        config(['services.api_bible.pending_verification' => ['KJV']]);
        $kjv = BibleTranslation::create([
            'code' => 'KJV', 'name' => 'King James Version', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true, 'is_active' => true,
        ]);

        $this->assertFalse($kjv->isPendingVerification());
    }

    // ---------------------------------------------------------------
    // Seeder idempotency
    // ---------------------------------------------------------------

    public function test_translation_seeder_is_idempotent_and_never_duplicates_rows(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(BibleTranslationSeeder::class);
        $first = BibleTranslation::orderBy('code')->get(['id', 'code', 'api_bible_id'])->toArray();

        $this->seed(BibleTranslationSeeder::class);
        $this->seed(BibleTranslationSeeder::class);
        $after = BibleTranslation::orderBy('code')->get(['id', 'code', 'api_bible_id'])->toArray();

        $this->assertSame($first, $after);
        $this->assertSame(
            BibleTranslation::count(),
            BibleTranslation::query()->distinct()->count('code'),
            'Every translation code appears exactly once.'
        );
        $this->assertSame(self::ASV_ID, BibleTranslation::where('code', 'ASV')->value('api_bible_id'));
        $this->assertSame(0, BibleVerse::count(), 'The translation seeder never writes verse text.');

        // Restricted translations stay restricted after seeding.
        $states = BibleTranslation::all()->mapWithKeys(fn ($t) => [$t->code => $t->availabilityState(false)]);
        $this->assertSame('license_required', $states['NLT']);
        $this->assertSame('license_required', $states['ASV']);
        foreach (['NIV', 'ESV', 'NASB', 'CSB', 'FR'] as $code) {
            $this->assertSame('not_in_account', $states[$code], $code);
            $this->assertNull(BibleTranslation::where('code', $code)->value('api_bible_id'), "{$code} has no invented API.Bible ID");
        }
        $this->assertSame('remote', $states['WEB']);
        $this->assertSame('remote', $states['BSB']);
    }

    public function test_database_seeder_never_overwrites_existing_verse_text(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(BibleTranslationSeeder::class);
        $this->seed(\Database\Seeders\BibleBookSeeder::class);

        $kjv = BibleTranslation::where('code', 'KJV')->firstOrFail();
        $genesis = BibleBook::where('name', 'Genesis')->firstOrFail();
        // Stand-in for an already-imported corpus row whose text must survive.
        $existing = BibleVerse::create([
            'bible_book_id' => $genesis->id, 'translation_id' => $kjv->id,
            'chapter' => 1, 'verse' => 28, 'text' => 'IMPORTED CORPUS TEXT',
        ]);

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame('IMPORTED CORPUS TEXT', $existing->fresh()->text);
        $this->assertSame(31, BibleVerse::where(['translation_id' => $kjv->id, 'bible_book_id' => $genesis->id, 'chapter' => 1])->count());
    }

    public function test_sample_seeder_genesis_1_28_is_the_complete_kjv_verse(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $text = BibleVerse::where(['chapter' => 1, 'verse' => 28])->value('text');

        $this->assertStringStartsWith('And God blessed them', $text);
        $this->assertStringEndsWith('over every living thing that moveth upon the earth.', $text);
    }

    // ---------------------------------------------------------------
    // Migration portability
    // ---------------------------------------------------------------

    public function test_bible_verse_uniqueness_is_scoped_to_translation_on_a_fresh_schema(): void
    {
        $index = collect(Schema::getIndexes('bible_verses'))->firstWhere('name', 'bible_verse_unique');

        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
        $this->assertEqualsCanonicalizing(['bible_book_id', 'chapter', 'verse', 'translation_id'], $index['columns']);
        $this->assertTrue(Schema::hasColumn('bible_reading_histories', 'expires_at'));
        $this->assertTrue(Schema::hasColumn('bible_translations', 'api_bible_id'));
    }

    // ---------------------------------------------------------------
    // Usage accounting
    // ---------------------------------------------------------------

    public function test_usage_distinguishes_upstream_calls_from_cache_hits(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapter())]);
        $client = app(ApiBibleClient::class);

        $client->chapter(self::WEB_ID, 'JHN', 3);
        $client->chapter(self::WEB_ID, 'JHN', 3);
        $client->chapter(self::WEB_ID, 'JHN', 3);

        $usage = $this->usage();
        $this->assertSame(1, $usage['counts']['upstream_success']);
        $this->assertSame(2, $usage['counts']['cache_hit']);
        $this->assertSame(1, $usage['upstream_calls']);
        Http::assertSentCount(1);
    }

    public function test_usage_classifies_errors_rate_limits_and_network_failures(): void
    {
        Http::fakeSequence(self::BASE.'/*')
            ->push(['message' => 'boom'], 503)
            ->push(['message' => 'nope'], 404)
            ->pushFailedConnection()
            ->push(['message' => 'slow down'], 429, ['Retry-After' => '120']);
        $client = app(ApiBibleClient::class);

        foreach (['GEN', 'EXO', 'LEV', 'NUM', 'DEU'] as $book) {
            try {
                $client->chapter(self::WEB_ID, $book, 1);
            } catch (ApiBibleException) {
                // expected
            }
        }

        $counts = $this->usage()['counts'];
        $this->assertSame(2, $counts['upstream_error']);   // 503 + 404
        $this->assertSame(1, $counts['network_error']);
        $this->assertSame(1, $counts['rate_limited']);
        $this->assertSame(1, $counts['suppressed']);       // DEU skipped during cooldown
        $this->assertSame(0, $counts['upstream_success']);
        $this->assertSame(3, $this->usage()['upstream_calls']);
        Http::assertSentCount(4);
    }

    public function test_failures_are_never_retried_automatically(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'boom'], 503)]);

        try {
            app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);
        } catch (ApiBibleException) {
        }

        Http::assertSentCount(1);
    }

    public function test_usage_counters_are_per_calendar_month(): void
    {
        $this->travelTo(now('UTC')->startOfMonth()->addDays(3));
        Http::fake([self::BASE.'/*' => Http::response($this->chapter())]);

        app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);
        $this->assertSame(1, $this->usage()['upstream_calls']);

        $this->travelTo(now('UTC')->addMonthNoOverflow()->startOfMonth()->addHour());
        $this->assertSame(0, $this->usage()['upstream_calls']);
    }

    public function test_warning_and_limit_alerts_are_logged_once_per_month_without_secrets_or_text(): void
    {
        Log::spy();
        config(['services.api_bible.monthly_limit' => 3, 'services.api_bible.warning_threshold' => 0.6]);
        Http::fake(fn () => Http::response($this->chapter()));
        $client = app(ApiBibleClient::class);

        foreach (['GEN', 'EXO', 'LEV', 'NUM'] as $book) {
            try {
                $client->chapter(self::WEB_ID, $book, 1);
            } catch (ApiBibleException) {
                // the faked verse belongs to JHN.3, so these chapters are "empty"; still upstream calls
            }
        }

        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg) => $level === 'warning' && str_contains($msg, 'warning level'))->once();
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg) => $level === 'error' && str_contains($msg, 'limit level'))->once();
        Log::shouldHaveReceived('log')->withArgs(function ($level, $msg, $context) {
            $flat = $msg.json_encode($context);
            $this->assertStringNotContainsString('test-key-not-real', $flat);
            $this->assertStringNotContainsString('loved the world', $flat);
            $this->assertStringContainsString('API.Bible dashboard', $flat);

            return true;
        });
    }

    public function test_usage_accounting_failures_never_break_reading(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapter())]);
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('has')->andReturn(false);
        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('add')->andThrow(new \RuntimeException('cache down'));

        $chapter = app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);

        $this->assertSame(1, $chapter->verses->count());
    }

    public function test_usage_command_reports_estimates_and_points_to_the_dashboard(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapter())]);
        app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);
        app(ApiBibleClient::class)->chapter(self::WEB_ID, 'JHN', 3);

        $this->artisan('bible:api-usage')
            ->expectsOutputToContain('Estimated upstream calls: 1 of 5000')
            ->expectsOutputToContain('authoritative')
            ->assertSuccessful();

        $this->artisan('bible:api-usage', ['--month' => 'nonsense'])->assertFailed();
    }

    // ---------------------------------------------------------------
    // 429 / Retry-After
    // ---------------------------------------------------------------

    public function test_retry_after_is_honoured_and_bounded(): void
    {
        Http::fake([self::BASE.'/*' => Http::sequence()
            ->push(['message' => 'slow down'], 429, ['Retry-After' => '7200'])
            ->push($this->chapter())]);
        $client = app(ApiBibleClient::class);

        try {
            $client->chapter(self::WEB_ID, 'JHN', 3);
        } catch (ApiBibleException $e) {
            $this->assertSame(ApiBibleException::RATE_LIMITED, $e->reason);
        }
        $this->assertTrue($client->isCoolingDown());

        $this->travel(59)->minutes();
        $this->assertTrue($client->isCoolingDown());
        $this->travel(2)->minutes(); // capped at one hour, not two
        $this->assertFalse($client->isCoolingDown());

        $this->assertSame(1, $client->chapter(self::WEB_ID, 'JHN', 3)->verses->count());
        Http::assertSentCount(2);
    }

    public function test_retry_after_http_dates_are_understood(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'slow down'], 429, [
            'Retry-After' => now()->addMinutes(10)->toRfc7231String(),
        ])]);
        $client = app(ApiBibleClient::class);

        try {
            $client->chapter(self::WEB_ID, 'JHN', 3);
        } catch (ApiBibleException) {
        }

        $this->travel(9)->minutes();
        $this->assertTrue($client->isCoolingDown());
        $this->travel(2)->minutes();
        $this->assertFalse($client->isCoolingDown());
    }

    public function test_network_failure_logs_omit_the_request_url_and_search_query(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for '
            .self::BASE.'/bibles/'.self::WEB_ID.'/search?query=my%20private%20prayer'
        ));

        try {
            app(ApiBibleClient::class)->search(self::WEB_ID, 'my private prayer');
        } catch (ApiBibleException) {
        }

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context) {
            $this->assertStringNotContainsString('private', json_encode($context));
            $this->assertStringContainsString('cURL error 28', $context['error']);

            return true;
        })->once();
    }
}
