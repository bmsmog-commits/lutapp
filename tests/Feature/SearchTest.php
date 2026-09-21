<?php

namespace Tests\Feature;

use App\Models\AudioResource;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\Job;
use App\Models\Language;
use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\OrganizationMember;
use App\Models\Resource as LibraryResource;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    // GLOBAL SEARCH

    public function test_global_search_returns_results_across_multiple_types(): void
    {
        $owner = User::factory()->create();
        Organization::create(['owner_id' => $owner->id, 'name' => 'Findable Chapel', 'slug' => 'findable-chapel', 'type' => 'church', 'visibility' => 'public']);
        Job::create(['user_id' => $owner->id, 'title' => 'Findable Designer', 'slug' => 'findable-designer', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);

        $response = $this->get(route('search.index', ['q' => 'Findable']));

        $response->assertOk()->assertSee('Findable Chapel')->assertSee('Findable Designer');
    }

    public function test_global_search_normalizes_results_with_type_identification(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Unique Marker Job', 'slug' => 'unique-marker-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);

        $response = $this->get(route('search.index', ['q' => 'Unique Marker']));

        $response->assertOk()->assertSee('Jobs')->assertSee('Unique Marker Job');
    }

    public function test_empty_query_shows_prompt_not_results(): void
    {
        $response = $this->get(route('search.index'));

        $response->assertOk()->assertSee('Type at least 2 characters');
    }

    public function test_no_results_empty_state_does_not_leak_private_record_counts(): void
    {
        $owner = User::factory()->create();
        $this->createOrganization($owner, 'private-nomatch', 'private');

        $response = $this->get(route('search.index', ['q' => 'zzzznomatchzzzz']));

        $response->assertOk()->assertSee('No results found');
        $response->assertDontSee('private');
    }

    // ORGANIZATIONS

    public function test_public_organization_is_searchable(): void
    {
        $owner = User::factory()->create();
        $this->createOrganization($owner, 'searchable-org', 'public');

        $response = $this->get(route('search.index', ['q' => 'Grace', 'type' => 'organizations']));

        $response->assertOk()->assertSee('Grace Chapel');
    }

    public function test_private_organization_is_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        $this->createOrganization($owner, 'private-search-org', 'private');

        $response = $this->get(route('search.index', ['q' => 'Grace', 'type' => 'organizations']));

        $response->assertOk()->assertDontSee('Grace Chapel');
    }

    public function test_organization_type_filter(): void
    {
        $owner = User::factory()->create();
        Organization::create(['owner_id' => $owner->id, 'name' => 'Filter Church', 'slug' => 'filter-church', 'type' => 'church', 'visibility' => 'public']);
        Organization::create(['owner_id' => $owner->id, 'name' => 'Filter Company', 'slug' => 'filter-company', 'type' => 'company', 'visibility' => 'public']);

        $response = $this->get(route('search.index', ['q' => 'Filter', 'type' => 'organizations', 'type_filter' => 'company']));

        $response->assertSee('Filter Company')->assertDontSee('Filter Church');
    }

    public function test_organization_location_filter(): void
    {
        $owner = User::factory()->create();
        Organization::create(['owner_id' => $owner->id, 'name' => 'Lagos Findable', 'slug' => 'lagos-findable', 'type' => 'church', 'visibility' => 'public', 'city' => 'Lagos']);
        Organization::create(['owner_id' => $owner->id, 'name' => 'Abuja Findable', 'slug' => 'abuja-findable', 'type' => 'church', 'visibility' => 'public', 'city' => 'Abuja']);

        $response = $this->get(route('search.index', ['q' => 'Findable', 'type' => 'organizations', 'city' => 'Lagos']));

        $response->assertSee('Lagos Findable')->assertDontSee('Abuja Findable');
    }

    // JOBS

    public function test_published_job_is_searchable(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Published Search Job', 'slug' => 'published-search-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('search.index', ['q' => 'Published Search', 'type' => 'jobs']))->assertSee('Published Search Job');
    }

    public function test_draft_job_is_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Draft Search Job', 'slug' => 'draft-search-job', 'status' => 'draft', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('search.index', ['q' => 'Draft Search', 'type' => 'jobs']))->assertDontSee('Draft Search Job');
    }

    public function test_closed_job_is_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Closed Search Job', 'slug' => 'closed-search-job', 'status' => 'closed', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('search.index', ['q' => 'Closed Search', 'type' => 'jobs']))->assertDontSee('Closed Search Job');
    }

    public function test_private_job_excluded_from_search_for_outsider(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Private Search Job', 'slug' => 'private-search-job', 'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('search.index', ['q' => 'Private Search', 'type' => 'jobs']))
            ->assertDontSee('Private Search Job');
    }

    public function test_job_organization_isolation_in_search(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a-search', 'public');
        $jobA = Job::create(['organization_id' => $orgA->id, 'title' => 'Org A Private Search Job', 'slug' => 'org-a-private-job', 'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote']);

        $ownerB = User::factory()->create();
        $this->createOrganization($ownerB, 'org-b-search', 'public');

        $this->actingAs($ownerB)->get(route('search.index', ['q' => 'Org A Private', 'type' => 'jobs']))
            ->assertDontSee('Org A Private Search Job');
    }

    // EVENTS

    public function test_published_event_is_searchable(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-org', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Findable Worship Night',
            'slug' => 'findable-worship-night', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);

        $this->get(route('search.index', ['q' => 'Findable Worship', 'type' => 'events']))->assertSee('Findable Worship Night');
    }

    public function test_draft_event_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-org-2', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Draft Search Event',
            'slug' => 'draft-search-event', 'status' => 'draft', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);

        $this->get(route('search.index', ['q' => 'Draft Search', 'type' => 'events']))->assertDontSee('Draft Search Event');
    }

    public function test_cancelled_event_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-org-3', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Cancelled Search Event',
            'slug' => 'cancelled-search-event', 'status' => 'cancelled', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);

        $this->get(route('search.index', ['q' => 'Cancelled Search', 'type' => 'events']))->assertDontSee('Cancelled Search Event');
    }

    public function test_private_event_excluded_from_search(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-org-4', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Private Search Event',
            'slug' => 'private-search-event', 'status' => 'published', 'visibility' => 'private',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('search.index', ['q' => 'Private Search', 'type' => 'events']))
            ->assertDontSee('Private Search Event');
    }

    public function test_event_organization_isolation_in_search(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'event-iso-a', 'public');
        OrganizationEvent::create([
            'organization_id' => $orgA->id, 'creator_id' => $ownerA->id, 'title' => 'Org A Private Search Event',
            'slug' => 'org-a-private-event', 'status' => 'published', 'visibility' => 'private',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $ownerB = User::factory()->create();

        $this->actingAs($ownerB)->get(route('search.index', ['q' => 'Org A Private', 'type' => 'events']))
            ->assertDontSee('Org A Private Search Event');
    }

    // RESOURCES

    public function test_published_resource_is_searchable(): void
    {
        $user = User::factory()->create();
        LibraryResource::create([
            'user_id' => $user->id, 'type' => 'book', 'title' => 'Findable Leadership Guide',
            'slug' => 'findable-leadership-guide', 'status' => 'published', 'visibility' => 'public',
        ]);

        $this->get(route('search.index', ['q' => 'Findable Leadership', 'type' => 'resources']))->assertSee('Findable Leadership Guide');
    }

    public function test_archived_resource_excluded_from_search(): void
    {
        $user = User::factory()->create();
        LibraryResource::create([
            'user_id' => $user->id, 'type' => 'book', 'title' => 'Archived Search Book',
            'slug' => 'archived-search-book', 'status' => 'archived', 'visibility' => 'public',
        ]);

        $this->get(route('search.index', ['q' => 'Archived Search', 'type' => 'resources']))->assertDontSee('Archived Search Book');
    }

    public function test_private_resource_excluded_from_search(): void
    {
        $user = User::factory()->create();
        LibraryResource::create([
            'user_id' => $user->id, 'type' => 'book', 'title' => 'Private Search Book',
            'slug' => 'private-search-book', 'status' => 'published', 'visibility' => 'private',
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('search.index', ['q' => 'Private Search', 'type' => 'resources']))
            ->assertDontSee('Private Search Book');
    }

    public function test_resource_language_filter(): void
    {
        $user = User::factory()->create();
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá']);
        LibraryResource::create(['user_id' => $user->id, 'type' => 'book', 'title' => 'Yoruba Findable Book', 'slug' => 'yoruba-findable-book', 'status' => 'published', 'visibility' => 'public', 'language_id' => $language->id]);
        LibraryResource::create(['user_id' => $user->id, 'type' => 'book', 'title' => 'English Findable Book', 'slug' => 'english-findable-book', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('search.index', ['q' => 'Findable', 'type' => 'resources', 'language_id' => $language->id]))
            ->assertSee('Yoruba Findable Book')->assertDontSee('English Findable Book');
    }

    // AUDIO

    public function test_published_audio_is_searchable(): void
    {
        $user = User::factory()->create();
        AudioResource::create(['user_id' => $user->id, 'title' => 'Findable Worship Song', 'slug' => 'findable-worship-song', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('search.index', ['q' => 'Findable Worship', 'type' => 'audio']))->assertSee('Findable Worship Song');
    }

    public function test_archived_audio_excluded_from_search(): void
    {
        $user = User::factory()->create();
        AudioResource::create(['user_id' => $user->id, 'title' => 'Archived Search Song', 'slug' => 'archived-search-song', 'status' => 'archived', 'visibility' => 'public']);

        $this->get(route('search.index', ['q' => 'Archived Search', 'type' => 'audio']))->assertDontSee('Archived Search Song');
    }

    public function test_private_audio_excluded_from_search(): void
    {
        $user = User::factory()->create();
        AudioResource::create(['user_id' => $user->id, 'title' => 'Private Search Song', 'slug' => 'private-search-song', 'status' => 'published', 'visibility' => 'private']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('search.index', ['q' => 'Private Search', 'type' => 'audio']))
            ->assertDontSee('Private Search Song');
    }

    public function test_audio_category_and_creator_filters(): void
    {
        $user = User::factory()->create();
        AudioResource::create(['user_id' => $user->id, 'title' => 'Filterable Sermon', 'slug' => 'filterable-sermon', 'status' => 'published', 'visibility' => 'public', 'category' => 'Sermon', 'creator_name' => 'Pastor Ade']);
        AudioResource::create(['user_id' => $user->id, 'title' => 'Filterable Worship', 'slug' => 'filterable-worship', 'status' => 'published', 'visibility' => 'public', 'category' => 'Worship', 'creator_name' => 'Choir']);

        $this->get(route('search.index', ['q' => 'Filterable', 'type' => 'audio', 'category' => 'Sermon']))
            ->assertSee('Filterable Sermon')->assertDontSee('Filterable Worship');

        $this->get(route('search.index', ['q' => 'Filterable', 'type' => 'audio', 'creator' => 'Ade']))
            ->assertSee('Filterable Sermon')->assertDontSee('Filterable Worship');
    }

    public function test_audio_organization_isolation_in_search(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'audio-iso-a', 'public');
        AudioResource::create(['organization_id' => $orgA->id, 'title' => 'Org A Private Search Audio', 'slug' => 'org-a-private-audio', 'status' => 'published', 'visibility' => 'private']);
        $ownerB = User::factory()->create();

        $this->actingAs($ownerB)->get(route('search.index', ['q' => 'Org A Private', 'type' => 'audio']))
            ->assertDontSee('Org A Private Search Audio');
    }

    // BIBLE

    public function test_bible_verse_is_searchable(): void
    {
        $translation = BibleTranslation::create(['code' => 'KJV', 'name' => 'King James Version', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $book = \App\Models\BibleBook::create(['name' => 'John', 'abbreviation' => 'Jn', 'testament' => 'new', 'sort_order' => 43, 'chapters_count' => 21]);
        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 3, 'verse' => 16, 'translation_id' => $translation->id, 'text' => 'For God so loved the world uniquemarkertext']);

        $this->get(route('search.index', ['q' => 'uniquemarkertext', 'type' => 'bible']))->assertSee('John 3:16');
    }

    public function test_bible_search_respects_book_filter(): void
    {
        $translation = BibleTranslation::create(['code' => 'KJV2', 'name' => 'KJV Two', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $john = \App\Models\BibleBook::create(['name' => 'John', 'abbreviation' => 'Jn', 'testament' => 'new', 'sort_order' => 43, 'chapters_count' => 21]);
        $mark = \App\Models\BibleBook::create(['name' => 'Mark', 'abbreviation' => 'Mk', 'testament' => 'new', 'sort_order' => 41, 'chapters_count' => 16]);
        BibleVerse::create(['bible_book_id' => $john->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'filterableverseword in John']);
        BibleVerse::create(['bible_book_id' => $mark->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'filterableverseword in Mark']);

        $response = $this->get(route('search.index', ['q' => 'filterableverseword', 'type' => 'bible', 'book_id' => $john->id, 'translation_id' => $translation->id]));

        $response->assertSee('John 1:1')->assertDontSee('Mark 1:1');
    }

    public function test_bible_search_result_links_to_bible_index(): void
    {
        $translation = BibleTranslation::create(['code' => 'KJV3', 'name' => 'KJV Three', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $book = \App\Models\BibleBook::create(['name' => 'Genesis', 'abbreviation' => 'Gen', 'testament' => 'old', 'sort_order' => 1, 'chapters_count' => 50]);
        BibleVerse::create(['bible_book_id' => $book->id, 'chapter' => 1, 'verse' => 1, 'translation_id' => $translation->id, 'text' => 'linkverseuniquephrase']);

        $response = $this->get(route('search.index', ['q' => 'linkverseuniquephrase', 'type' => 'bible', 'translation_id' => $translation->id]));

        $response->assertOk();
        $expectedUrl = route('bible.index', ['book' => $book->id, 'chapter' => 1, 'translation_id' => $translation->id]);
        $response->assertSee(htmlspecialchars($expectedUrl), false);
    }

    // USERS / PEOPLE

    public function test_authenticated_user_can_search_people(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Findable Person']);

        $this->actingAs($searcher)->get(route('search.index', ['q' => 'Findable Person', 'type' => 'people']))
            ->assertSee('Findable Person');
    }

    public function test_guest_gets_no_people_search_results(): void
    {
        User::factory()->create(['name' => 'Guest Target Person']);

        $this->get(route('search.index', ['q' => 'Guest Target', 'type' => 'people']))
            ->assertDontSee('Guest Target Person');
    }

    public function test_people_search_does_not_expose_email(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Email Safe Person', 'email' => 'supersecret@example.com']);

        $this->actingAs($searcher)->get(route('search.index', ['q' => 'Email Safe', 'type' => 'people']))
            ->assertDontSee('supersecret@example.com');
    }

    // SECURITY / CROSS-ORGANIZATION

    public function test_manipulated_organization_id_filter_does_not_expose_private_records(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'manip-org', 'private');
        Job::create(['organization_id' => $organization->id, 'title' => 'Manip Private Job', 'slug' => 'manip-private-job', 'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('search.index', ['q' => 'Manip Private', 'type' => 'jobs', 'organization_id' => $organization->id]))
            ->assertDontSee('Manip Private Job');
    }

    public function test_authorized_organization_member_sees_their_own_private_resource_in_search(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'member-visible-org', 'public');
        LibraryResource::create([
            'organization_id' => $organization->id, 'type' => 'book', 'title' => 'Member Visible Findable Book',
            'slug' => 'member-visible-findable-book', 'status' => 'published', 'visibility' => 'private',
        ]);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('search.index', ['q' => 'Member Visible Findable', 'type' => 'resources']))
            ->assertSee('Member Visible Findable Book');
    }

    // PAGINATION

    public function test_search_results_are_paginated(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 20) as $i) {
            Job::create(['user_id' => $user->id, 'title' => "Paginated Search Job {$i}", 'slug' => "paginated-search-job-{$i}", 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        }

        $response = $this->get(route('search.index', ['q' => 'Paginated Search', 'type' => 'jobs']));

        $response->assertOk();
        $this->assertSame(15, $response->viewData('results')->count());
        $this->assertTrue($response->viewData('results')->hasMorePages());
    }
}
