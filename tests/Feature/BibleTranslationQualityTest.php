<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\Note;
use App\Models\User;
use App\Services\Bible\ApiBible\ApiBibleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 27.9 — translation status accuracy, FUMS provenance, cache expiry,
 * remote search and preservation of the reading experience. All API.Bible
 * traffic is faked; stray requests fail the test.
 */
class BibleTranslationQualityTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://rest.api.bible/v1';
    private const KEY = 'secret-test-key-0123456789';
    private const WEB_ID = '9879dbb7cfe39e4d-01';
    private const BSB_ID = 'bba9f40183526463-01';
    private const NLT_ID = 'd6e14a625393b4da-01';

    /** A realistic 248-character FUMS v3 token, as API.Bible issues them. */
    private string $fumsToken;

    private BibleBook $john;
    private BibleBook $genesis;
    private BibleTranslation $kjv;
    private BibleTranslation $web;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::flush();

        config([
            'services.api_bible.key' => self::KEY,
            'services.api_bible.base_url' => self::BASE,
            'services.api_bible.cache_ttl' => 86400,
            'services.api_bible.allow_copyrighted' => false,
        ]);

        $this->fumsToken = 'fv3.'.str_repeat('Ab3_', 61);

        $this->genesis = BibleBook::create(['sort_order' => 1, 'name' => 'Genesis', 'abbreviation' => 'Gen', 'testament' => 'Old', 'chapters_count' => 50]);
        $this->john = BibleBook::create(['sort_order' => 43, 'name' => 'John', 'abbreviation' => 'Joh', 'testament' => 'New', 'chapters_count' => 21]);

        $this->kjv = BibleTranslation::create([
            'code' => 'KJV', 'name' => 'King James Version', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true, 'is_active' => true,
        ]);
        BibleVerse::create(['bible_book_id' => $this->genesis->id, 'translation_id' => $this->kjv->id, 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning God created the heaven and the earth.']);
        BibleVerse::create(['bible_book_id' => $this->john->id, 'translation_id' => $this->kjv->id, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world, that he gave his only begotten Son.']);

        $this->web = BibleTranslation::create([
            'code' => 'WEB', 'name' => 'World English Bible', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true,
            'api_bible_id' => self::WEB_ID, 'is_active' => true,
        ]);
    }

    private function bsb(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'BSB', 'name' => 'Berean Standard Bible', 'language' => 'English',
            'license' => 'Public Domain', 'public_domain' => true, 'redistributable' => true,
            'api_bible_id' => self::BSB_ID, 'is_active' => true,
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

    private function niv(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'NIV', 'name' => 'New International Version', 'language' => 'English',
            'license' => 'Proprietary', 'public_domain' => false, 'redistributable' => false,
            'license_type' => 'licensed_remote', 'availability_status' => 'not_in_account', 'is_active' => true,
        ]);
    }

    private function chapterResponse(?string $token = 'default', string $verseText = 'Remote WEB verse sixteen.'): array
    {
        $meta = $token === null ? [] : ['fumsToken' => $token === 'default' ? $this->fumsToken : $token];

        return [
            'data' => [
                'id' => 'JHN.3', 'bibleId' => self::WEB_ID, 'reference' => 'John 3', 'copyright' => 'PUBLIC DOMAIN',
                'content' => [[
                    'name' => 'para', 'type' => 'tag', 'attrs' => ['style' => 'p'],
                    'items' => [['text' => $verseText, 'type' => 'text', 'attrs' => ['verseId' => 'JHN.3.16']]],
                ]],
            ],
            'meta' => $meta,
        ];
    }

    private function readerUrl(BibleTranslation $translation, ?BibleBook $book = null, int $chapter = 3): string
    {
        return route('bible.index', ['book' => ($book ?? $this->john)->id, 'chapter' => $chapter, 'translation_id' => $translation->id]);
    }

    // ---------------------------------------------------------------
    // 1. Translation status and licence gating
    // ---------------------------------------------------------------

    public function test_each_translation_reports_an_accurate_state(): void
    {
        $bsb = $this->bsb();
        $nlt = $this->nlt();
        $niv = $this->niv();

        $this->assertSame('available', $this->kjv->availabilityState());
        $this->assertSame('remote', $this->web->availabilityState());
        $this->assertSame('remote', $bsb->availabilityState());
        $this->assertSame('license_required', $nlt->availabilityState());
        $this->assertSame('not_in_account', $niv->availabilityState());
    }

    public function test_stored_metadata_can_never_claim_remote_or_a_temporary_outage(): void
    {
        foreach (['remote', 'temporarily_unavailable', 'available'] as $i => $claimed) {
            $lying = BibleTranslation::create([
                'code' => 'LIE'.$i, 'name' => 'Lying '.$i, 'language' => 'English',
                'license' => 'Proprietary', 'public_domain' => false, 'redistributable' => false,
                'availability_status' => $claimed, 'is_active' => true,
            ]);

            $this->assertSame('licensed_unavailable', $lying->availabilityState(), "Stored '{$claimed}' must not be trusted.");
        }
    }

    public function test_copyrighted_remote_translations_need_the_explicit_licence_flag(): void
    {
        $nlt = $this->nlt();
        $this->assertFalse($nlt->canBeServedRemotely());

        config(['services.api_bible.allow_copyrighted' => true]);
        $this->assertTrue($nlt->fresh()->canBeServedRemotely());
        // A translation with no API.Bible link never becomes remote, flag or not.
        $this->assertFalse($this->niv()->canBeServedRemotely());
    }

    // ---------------------------------------------------------------
    // 5. Selector accuracy
    // ---------------------------------------------------------------

    public function test_selector_distinguishes_every_state(): void
    {
        $this->bsb();
        $this->nlt();
        $this->niv();
        BibleTranslation::create([
            'code' => 'YOR', 'name' => 'Yoruba Bible', 'language' => 'Yoruba',
            'license' => 'Unverified', 'public_domain' => false, 'redistributable' => false,
            'availability_status' => 'not_available', 'is_active' => true,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('bible.index'))->assertOk();

        foreach (['available', 'remote', 'license_required', 'not_in_account', 'not_available'] as $state) {
            $response->assertSee('data-state="'.$state.'"', false);
            $response->assertSee(__('messages.translation_state_'.$state));
        }
        $this->assertSame('Available locally', __('messages.translation_state_available'));
        $this->assertSame('Available remotely', __('messages.translation_state_remote'));
        $this->assertSame('Licence verification required', __('messages.translation_state_license_required'));
        $this->assertSame('Not available to this account', __('messages.translation_state_not_in_account'));
    }

    public function test_a_temporary_failure_is_shown_as_temporary_and_never_persisted(): void
    {
        Http::fakeSequence(self::BASE.'/bibles/'.self::WEB_ID.'/chapters/*')
            ->pushFailedConnection()
            ->push($this->chapterResponse());

        $user = User::factory()->create();

        $this->actingAs($user)->get($this->readerUrl($this->web))
            ->assertOk()
            ->assertSee('data-remote-error="unavailable"', false)
            ->assertSee('data-state="temporarily_unavailable"', false)
            ->assertSee(__('messages.translation_state_temporarily_unavailable'))
            ->assertDontSee('data-state="license_required"', false);

        // Nothing about the outage was written to the catalog.
        $this->assertNull($this->web->fresh()->availability_status);
        $this->assertSame('remote', $this->web->fresh()->availabilityState());

        // Once the provider recovers, the same translation reads normally.
        $this->actingAs($user)->get($this->readerUrl($this->web))
            ->assertOk()
            ->assertSee('Remote WEB verse sixteen.')
            ->assertSee('data-state="remote"', false);
    }

    public function test_an_authorisation_failure_reports_not_available_to_this_account_for_that_request_only(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Forbidden'], 403)]);

        $this->actingAs(User::factory()->create())->get($this->readerUrl($this->web))
            ->assertOk()
            ->assertSee('data-state="not_in_account"', false)
            ->assertSee(__('messages.api_bible_error_unauthorized'));

        $this->assertNull($this->web->fresh()->availability_status);
    }

    public function test_a_rate_limit_cooldown_marks_all_remote_translations_temporarily_unavailable(): void
    {
        $this->bsb();
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Too Many Requests'], 429, ['Retry-After' => '30'])]);

        $user = User::factory()->create();
        $this->actingAs($user)->get($this->readerUrl($this->web))->assertOk();

        $response = $this->actingAs($user)->get(route('bible.index', ['translation_id' => $this->kjv->id]))->assertOk();
        $response->assertSee('data-state="temporarily_unavailable"', false);
        $response->assertDontSee('data-state="remote"', false);
        $response->assertSee('data-state="available"', false); // KJV unaffected

        Http::assertSentCount(1);
    }

    // ---------------------------------------------------------------
    // 2. FUMS
    // ---------------------------------------------------------------

    public function test_fums_reports_the_issued_token_and_marks_cache_hits(): void
    {
        Http::fake([self::BASE.'/bibles/'.self::WEB_ID.'/chapters/*' => Http::response($this->chapterResponse())]);
        $user = User::factory()->create();

        $live = $this->actingAs($user)->get($this->readerUrl($this->web))->assertOk();
        $live->assertSee('https://pkg.api.bible/fumsV3.min.js', false);
        $live->assertSee('data-fums-source="live"', false);
        $live->assertSee(json_encode($this->fumsToken), false);
        $live->assertSee("window.fums('trackView', token)", false);

        $cached = $this->actingAs($user)->get($this->readerUrl($this->web))->assertOk();
        $cached->assertSee('data-fums-source="cache"', false);
        // The cache preserves exactly the token API.Bible issued — nothing new is invented.
        $cached->assertSee(json_encode($this->fumsToken), false);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['fums-version'] === '3');
    }

    public function test_no_fums_report_is_rendered_when_the_response_has_no_token(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse(token: null))]);

        $this->actingAs(User::factory()->create())->get($this->readerUrl($this->web))
            ->assertOk()
            ->assertSee('Remote WEB verse sixteen.')
            ->assertSee('data-testid="api-bible-attribution"', false)
            ->assertDontSee('data-testid="fums-report"', false)
            ->assertDontSee('fumsV3.min.js', false);
    }

    public function test_fums_reporting_is_fault_tolerant_and_safely_encoded(): void
    {
        $hostile = "tok'</script><script>alert(1)</script>";
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse(token: $hostile))]);

        $html = $this->actingAs(User::factory()->create())->get($this->readerUrl($this->web))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('catch (e)', $html);
        $this->assertStringContainsString("typeof window.fums === 'function'", $html);
    }

    // ---------------------------------------------------------------
    // 3. Cache keys, expiry and failure handling
    // ---------------------------------------------------------------

    public function test_cached_chapters_expire_after_the_configured_ttl(): void
    {
        config(['services.api_bible.cache_ttl' => 3600]);
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $client = app(ApiBibleClient::class);

        $this->assertFalse($client->chapter(self::WEB_ID, 'JHN', 3)->fromCache);
        $this->travel(59)->minutes();
        $this->assertTrue($client->chapter(self::WEB_ID, 'JHN', 3)->fromCache);
        $this->travel(2)->minutes();
        $this->assertFalse($client->chapter(self::WEB_ID, 'JHN', 3)->fromCache);

        Http::assertSentCount(2);
    }

    public function test_lowering_the_ttl_evicts_entries_older_than_the_new_limit(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $client = app(ApiBibleClient::class);

        $client->chapter(self::WEB_ID, 'JHN', 3); // cached under a 24h TTL
        $this->travel(2)->hours();
        config(['services.api_bible.cache_ttl' => 3600]);

        $this->assertFalse($client->chapter(self::WEB_ID, 'JHN', 3)->fromCache);
        Http::assertSentCount(2);
    }

    public function test_cache_never_outlives_fourteen_days_even_if_the_store_does(): void
    {
        config(['services.api_bible.cache_ttl' => 60 * 60 * 24 * 365]);
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $client = app(ApiBibleClient::class);

        $client->chapter(self::WEB_ID, 'JHN', 3);
        // Simulate a store that ignores expiry by rewriting the entry forever.
        $key = 'api_bible:v2:chapter:'.self::WEB_ID.':JHN.3';
        Cache::forever($key, Cache::get($key));

        $this->travel(14)->days();
        $this->travel(1)->minutes();
        $this->assertFalse($client->chapter(self::WEB_ID, 'JHN', 3)->fromCache);
    }

    public function test_cache_keys_distinguish_bible_book_and_chapter(): void
    {
        // Answer each chapter request with a verse belonging to THAT chapter.
        Http::fake(function (Request $request) {
            preg_match('#/chapters/([1-3A-Z]{3}\.\d+)#', $request->url(), $m);
            $payload = $this->chapterResponse();
            $payload['data']['content'][0]['items'][0]['attrs']['verseId'] = $m[1].'.1';

            return Http::response($payload);
        });
        $client = app(ApiBibleClient::class);

        $client->chapter(self::WEB_ID, 'JHN', 3);
        $client->chapter(self::WEB_ID, 'JHN', 4);
        $client->chapter(self::WEB_ID, 'GEN', 3);
        $client->chapter(self::BSB_ID, 'JHN', 3);
        $client->chapter(self::WEB_ID, 'JHN', 3); // the only repeat

        Http::assertSentCount(4);
    }

    public function test_cached_copyright_and_verses_survive_the_round_trip(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $client = app(ApiBibleClient::class);

        $live = $client->chapter(self::WEB_ID, 'JHN', 3);
        $cached = $client->chapter(self::WEB_ID, 'JHN', 3);

        $this->assertSame($live->copyright, $cached->copyright);
        $this->assertSame($live->reference, $cached->reference);
        $this->assertSame($live->fetchedAt, $cached->fetchedAt);
        $this->assertEquals($live->verses, $cached->verses);
    }

    public function test_failures_and_empty_responses_are_never_cached(): void
    {
        Http::fakeSequence(self::BASE.'/*')
            ->push(['message' => 'Server error'], 500)
            ->push(['data' => ['reference' => 'John 3', 'content' => []], 'meta' => []])
            ->push($this->chapterResponse());
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->readerUrl($this->web))->assertSee('data-remote-error="unavailable"', false);
        $this->actingAs($user)->get($this->readerUrl($this->web))->assertSee('data-remote-error="unavailable"', false);
        $this->actingAs($user)->get($this->readerUrl($this->web))->assertSee('Remote WEB verse sixteen.');

        Http::assertSentCount(3);
    }

    // ---------------------------------------------------------------
    // 4. Search
    // ---------------------------------------------------------------

    private function searchResponse(array $verses, int $total, int $offset = 0, array $passages = []): array
    {
        $data = ['query' => 'x', 'limit' => 25, 'offset' => $offset, 'total' => $total, 'verseCount' => count($verses), 'verses' => $verses];
        if ($passages) {
            $data = ['passages' => $passages];
        }

        return ['data' => $data, 'meta' => ['fumsToken' => $this->fumsToken]];
    }

    private function fakeCopyright(): array
    {
        return [self::BASE.'/bibles/'.self::WEB_ID => Http::response(['data' => ['id' => self::WEB_ID, 'copyright' => 'PUBLIC DOMAIN']])];
    }

    public function test_local_search_is_labelled_and_makes_no_remote_call(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'beginning', 'translation_id' => $this->kjv->id]))
            ->assertOk()
            ->assertSee('data-mode="local"', false)
            ->assertSee(__('messages.bible_search_mode_local', ['translation' => 'KJV']))
            ->assertSee('In the beginning God created');

        Http::assertNothingSent();
    }

    public function test_remote_search_returns_genuine_results_with_attribution_and_fums(): void
    {
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse([
                ['id' => 'JHN.3.3', 'orgId' => 'JHN.3.3', 'bookId' => 'JHN', 'bibleId' => self::WEB_ID, 'chapterId' => 'JHN.3', 'reference' => 'John 3:3', 'text' => 'unless one is born anew, he can’t see God’s Kingdom.'],
                ['id' => 'GEN.1.1', 'orgId' => 'GEN.1.1', 'bookId' => 'GEN', 'bibleId' => self::WEB_ID, 'chapterId' => 'GEN.1', 'reference' => 'Genesis 1:1', 'text' => 'In the beginning, God created the heavens and the earth.'],
            ], 2)),
            ...$this->fakeCopyright(),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'born anew', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertSee('data-mode="remote"', false)
            ->assertSee(__('messages.bible_search_mode_remote', ['translation' => 'WEB']))
            ->assertSee('John 3:3')
            ->assertSee('unless one is born anew', false)
            ->assertSee('data-testid="api-bible-attribution"', false)
            ->assertSee('PUBLIC DOMAIN')
            ->assertSee(json_encode($this->fumsToken), false);

        // Results are actionable: bookmark/highlight forms target the WEB verse.
        $response->assertSee('name="bible_book_id" value="'.$this->john->id.'"', false);
        $response->assertSee('name="translation_id" value="'.$this->web->id.'"', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/bibles/'.self::WEB_ID.'/search')
            && $r['query'] === 'born anew' && (int) $r['limit'] === 25 && (int) $r['offset'] === 0
            && $r['fums-version'] === '3' && $r->hasHeader('api-key', self::KEY));
        $this->assertSame(0, BibleVerse::where('translation_id', $this->web->id)->count());
    }

    public function test_remote_search_text_is_escaped(): void
    {
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse([
                ['id' => 'JHN.3.16', 'reference' => 'John 3:16', 'text' => 'Loved <img src=x onerror=alert(1)> the world'],
            ], 1)),
            ...$this->fakeCopyright(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'loved', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_remote_search_paginates_with_offsets(): void
    {
        $verse = ['id' => 'JHN.3.16', 'reference' => 'John 3:16', 'text' => 'For God so loved the world.'];
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse(array_fill(0, 25, $verse), 774, 25)),
            ...$this->fakeCopyright(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'love', 'translation_id' => $this->web->id, 'page' => 2]))
            ->assertOk()
            ->assertSee(__('messages.bible_search_showing', ['from' => 26, 'to' => 50, 'total' => 774]))
            ->assertSee('page=3', false);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/search') && (int) $r['offset'] === 25);
    }

    public function test_reference_queries_link_to_the_matching_chapter(): void
    {
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse([], 0, 0, [
                ['id' => 'JHN.3.16', 'bibleId' => self::WEB_ID, 'bookId' => 'JHN', 'chapterIds' => ['JHN.3'], 'reference' => 'John 3:16', 'content' => '<p>…</p>'],
            ])),
            ...$this->fakeCopyright(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'John 3:16', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertSee('data-testid="bible-search-passages"', false)
            ->assertSee(e(route('bible.index', ['book' => $this->john->id, 'chapter' => 3, 'translation_id' => $this->web->id])), false)
            ->assertDontSee('data-testid="bible-search-no-matches"', false);
    }

    public function test_an_empty_remote_result_is_not_presented_as_proof_of_absence(): void
    {
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse([], 0)),
            ...$this->fakeCopyright(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'born again', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertSee(__('messages.bible_search_no_matches', ['query' => 'born again', 'translation' => 'WEB']), false)
            ->assertSee(__('messages.bible_search_no_matches_caveat'), false)
            ->assertDontSee(__('messages.no_verses_found'));
    }

    public function test_repeat_searches_are_served_from_cache(): void
    {
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response($this->searchResponse([['id' => 'JHN.3.3', 'reference' => 'John 3:3', 'text' => 'born anew']], 1)),
            ...$this->fakeCopyright(),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('bible.search', ['q' => 'born anew', 'translation_id' => $this->web->id]))->assertOk();
        $this->actingAs($user)->get(route('bible.search', ['q' => '  Born   ANEW ', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertSee('data-fums-source="cache"', false);

        Http::assertSentCount(2); // one search + one copyright lookup, both cached
    }

    public function test_short_queries_never_reach_the_api(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'go', 'translation_id' => $this->web->id]))
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_unsearchable_translation_explains_itself_and_offers_kjv(): void
    {
        Http::fake();
        $nlt = $this->nlt();

        $this->actingAs(User::factory()->create())
            ->get(route('bible.search', ['q' => 'loved the world', 'translation_id' => $nlt->id]))
            ->assertOk()
            ->assertSee('data-testid="bible-search-unsupported"', false)
            ->assertSee(__('messages.bible_search_unsupported_title', ['translation' => 'NLT']))
            ->assertSee(e(route('bible.search', ['q' => 'loved the world', 'translation_id' => $this->kjv->id])), false)
            ->assertDontSee('data-testid="bible-search-no-matches"', false);

        Http::assertNothingSent();
    }

    public function test_remote_search_errors_keep_local_search_reachable(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Too Many Requests'], 429)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('bible.search', ['q' => 'loved', 'translation_id' => $this->web->id]))
            ->assertOk()
            ->assertSee('data-remote-error="rate_limited"', false)
            ->assertSee(__('messages.api_bible_error_rate_limited'))
            ->assertSee(__('messages.bible_search_in_local', ['translation' => 'KJV']));

        // Local search is completely unaffected by the provider failure.
        $this->actingAs($user)
            ->get(route('bible.search', ['q' => 'loved', 'translation_id' => $this->kjv->id]))
            ->assertOk()
            ->assertSee('For God so loved the world');

        Http::assertSentCount(1);
    }

    // ---------------------------------------------------------------
    // 6. Reading experience
    // ---------------------------------------------------------------

    public function test_users_without_a_preference_default_to_local_text(): void
    {
        $this->bsb(); // sorts before KJV alphabetically
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->get(route('bible.index', ['book' => $this->genesis->id, 'chapter' => 1]))
            ->assertOk()
            ->assertSee('In the beginning God created the heaven and the earth.');

        Http::assertNothingSent();
    }

    public function test_a_failed_remote_chapter_never_shows_kjv_text_under_the_remote_name(): void
    {
        Http::fake([self::BASE.'/*' => fn () => throw new ConnectionException('timeout')]);

        $this->actingAs(User::factory()->create())->get($this->readerUrl($this->web))
            ->assertOk()
            ->assertSee('World English Bible')
            ->assertDontSee('he gave his only begotten Son')
            ->assertSee(__('messages.translation_read_in', ['translation' => 'KJV']));
    }

    public function test_local_kjv_keeps_working_while_the_provider_is_down(): void
    {
        Http::fake([self::BASE.'/*' => fn () => throw new ConnectionException('down')]);

        $this->actingAs(User::factory()->create())
            ->get($this->readerUrl($this->kjv, $this->genesis, 1))
            ->assertOk()
            ->assertSee('In the beginning God created the heaven and the earth.');

        Http::assertNothingSent();
        $this->assertSame(2, BibleVerse::where('translation_id', $this->kjv->id)->count());
    }

    public function test_switching_translations_in_the_study_workspace_preserves_notes(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Born anew',
            'body' => 'John 3:3 (WEB) — new birth.',
            'color' => '#fff7b2',
            'bible_reference' => 'John 3 (WEB)',
            'bible_book_id' => $this->john->id,
            'bible_chapter' => 3,
            'bible_translation_id' => $this->web->id,
        ])->assertRedirect();

        $note = Note::where('user_id', $user->id)->sole();

        foreach ([$this->web, $this->kjv, $this->web] as $translation) {
            $this->actingAs($user)
                ->get(route('bible.study', ['book' => $this->john->id, 'chapter' => 3, 'translation_id' => $translation->id]))
                ->assertOk()
                ->assertSee('Born anew')
                ->assertSee('id="tab-bible"', false)
                ->assertSee('id="tab-notes"', false);
        }

        $fresh = $note->fresh();
        $this->assertSame('John 3:3 (WEB) — new birth.', $fresh->body);
        $this->assertSame('John 3 (WEB)', $fresh->bible_reference);
        $this->assertSame($this->web->id, (int) $fresh->bible_translation_id);
        $this->assertSame(1, Note::where('user_id', $user->id)->count());
    }

    public function test_remote_reading_history_appears_in_continue_reading_and_expires_after_24_hours(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->chapterResponse())]);
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->readerUrl($this->web))->assertOk();

        $this->actingAs($user)->get(route('bible.index', ['translation_id' => $this->kjv->id]))
            ->assertOk()
            ->assertSee('data-testid="continue-reading"', false)
            ->assertSee('WEB');

        $this->travel(25)->hours();

        $this->assertSame(0, BibleReadingHistory::where('user_id', $user->id)->notExpired()->count());
        $this->actingAs($user)->get(route('bible.reading-history'))
            ->assertOk()
            ->assertDontSee('John 3');
    }

    // ---------------------------------------------------------------
    // 7. Security
    // ---------------------------------------------------------------

    public function test_the_api_key_never_reaches_html_or_logs(): void
    {
        Log::spy();
        Http::fake([
            self::BASE.'/bibles/'.self::WEB_ID.'/chapters/*' => Http::response($this->chapterResponse()),
            self::BASE.'/bibles/'.self::WEB_ID.'/search*' => Http::response(['message' => 'boom'], 500),
        ]);
        $user = User::factory()->create();

        $pages = [
            $this->actingAs($user)->get($this->readerUrl($this->web))->getContent(),
            $this->actingAs($user)->get(route('bible.study', ['book' => $this->john->id, 'chapter' => 3, 'translation_id' => $this->web->id]))->getContent(),
            $this->actingAs($user)->get(route('bible.search', ['q' => 'loved', 'translation_id' => $this->web->id]))->getContent(),
        ];

        foreach ($pages as $html) {
            $this->assertStringNotContainsString(self::KEY, $html);
        }

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) {
            $this->assertStringNotContainsString(self::KEY, $message.json_encode($context));

            return true;
        });
    }
}
