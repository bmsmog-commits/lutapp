<?php

namespace Tests\Feature;

use App\Models\AudioResource;
use App\Models\Job;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Resource as LibraryResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createUserWithProfile(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create($userOverrides);
        $user->profile()->create(array_merge([
            'username' => 'user'.$user->id,
            'display_name' => $user->name,
        ], $profileOverrides));

        return $user->fresh();
    }

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    // ROUTE / ACCESS

    public function test_public_profile_is_viewable_by_a_guest(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Public Person'], ['username' => 'publicperson']);

        $this->get(route('users.show', 'publicperson'))->assertOk()->assertSee('Public Person');
    }

    public function test_nonexistent_username_returns_404(): void
    {
        $this->get(route('users.show', 'no-such-user-at-all'))->assertNotFound();
    }

    public function test_private_profile_is_not_publicly_accessible(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Private Person'], ['username' => 'privateperson']);
        $user->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        $this->get(route('users.show', 'privateperson'))->assertNotFound();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('users.show', 'privateperson'))->assertNotFound();
    }

    public function test_private_profile_direct_url_obeys_the_same_privacy_rule(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Direct URL Person'], ['username' => 'directurlperson']);
        $user->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        // Same URL shape a normal link would use — no query param or ID
        // manipulation bypasses the check.
        $this->get('/users/directurlperson')->assertNotFound();
    }

    public function test_owner_can_always_view_their_own_profile_page(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Self Viewer'], ['username' => 'selfviewer']);
        $user->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        $this->actingAs($user)->get(route('users.show', 'selfviewer'))->assertOk();
    }

    // PUBLIC DATA EXPOSURE

    public function test_public_profile_shows_bio_and_location(): void
    {
        $this->createUserWithProfile(['name' => 'Bio Person'], [
            'username' => 'bioperson', 'bio' => 'I love building things.', 'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $response = $this->get(route('users.show', 'bioperson'));

        $response->assertSee('I love building things.')->assertSee('Lagos')->assertSee('Nigeria');
    }

    public function test_public_profile_does_not_expose_phone_or_address(): void
    {
        $this->createUserWithProfile(['name' => 'Phone Person'], [
            'username' => 'phoneperson', 'phone' => '+2348000000000', 'address' => '123 Secret Street', 'postal_code' => '100001',
        ]);

        $response = $this->get(route('users.show', 'phoneperson'));

        $response->assertDontSee('+2348000000000')->assertDontSee('123 Secret Street')->assertDontSee('100001');
    }

    public function test_public_profile_does_not_expose_email(): void
    {
        $this->createUserWithProfile(['name' => 'Email Person', 'email' => 'private-email@example.com'], ['username' => 'emailperson']);

        $this->get(route('users.show', 'emailperson'))->assertDontSee('private-email@example.com');
    }

    // PROFILE PHOTO

    public function test_public_profile_shows_profile_photo_when_present(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Photo Person'], ['username' => 'photoperson']);
        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->image('a.jpg')->size(30),
        ]);

        $response = $this->get(route('users.show', 'photoperson'));

        $response->assertOk();
        $this->assertNotNull($user->profile->fresh()->profile_photo_media_id);
    }

    public function test_private_profile_photo_is_not_leaked_via_direct_media_url(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Locked Photo Person'], ['username' => 'lockedphotoperson']);
        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->image('a.jpg')->size(30),
        ]);
        $mediaId = $user->profile->fresh()->profile_photo_media_id;

        \Illuminate\Support\Facades\Auth::logout();
        $this->get(route('files.show', $mediaId))->assertNotFound();
    }

    // SEARCH / DISCOVERY

    public function test_discoverable_user_appears_in_unified_search(): void
    {
        $this->createUserWithProfile(['name' => 'Discoverable Person'], ['username' => 'discoverableperson']);

        $this->get(route('search.index', ['q' => 'Discoverable Person', 'type' => 'people']))
            ->assertSee(route('users.show', 'discoverableperson'), false);
    }

    public function test_private_user_excluded_from_search_results(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Hidden From Search'], ['username' => 'hiddenfromsearch']);
        $user->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        $response = $this->get(route('search.index', ['q' => 'Hidden From Search', 'type' => 'people']));

        $this->assertCount(0, $response->viewData('results'));
    }

    public function test_guest_can_now_see_discoverable_people_in_search(): void
    {
        $this->createUserWithProfile(['name' => 'Guest Visible Person'], ['username' => 'guestvisibleperson']);

        $response = $this->get(route('search.index', ['q' => 'Guest Visible Person', 'type' => 'people']));

        $this->assertCount(1, $response->viewData('results'));
    }

    public function test_search_filters_by_country(): void
    {
        $this->createUserWithProfile(['name' => 'Nigeria Search Person'], ['username' => 'nigeriasearchperson', 'country' => 'Nigeria']);
        $this->createUserWithProfile(['name' => 'Ghana Search Person'], ['username' => 'ghanasearchperson', 'country' => 'Ghana']);

        $response = $this->get(route('search.index', ['q' => 'Search Person', 'type' => 'people', 'country' => 'Ghana']));

        $results = $response->viewData('results');
        $this->assertCount(1, $results);
        $this->assertSame('Ghana Search Person', $results->first()->title);
    }

    public function test_search_pagination_works_for_people(): void
    {
        foreach (range(1, 20) as $i) {
            $this->createUserWithProfile(['name' => "Paginated Person {$i}"], ['username' => "paginatedperson{$i}"]);
        }

        $response = $this->get(route('search.index', ['q' => 'Paginated Person', 'type' => 'people']));

        $this->assertSame(15, $response->viewData('results')->count());
        $this->assertTrue($response->viewData('results')->hasMorePages());
    }

    // ORGANIZATION AFFILIATIONS

    public function test_public_organization_affiliation_appears_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Org Member Person'], ['username' => 'orgmemberperson']);
        $organization = $this->createOrganization($user, 'affiliation-org', 'public');

        $this->get(route('users.show', 'orgmemberperson'))->assertSee('Grace Chapel');
    }

    public function test_private_organization_affiliation_is_not_exposed(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Private Org Person'], ['username' => 'privateorgperson']);
        $this->createOrganization($user, 'private-affiliation-org', 'private');

        $this->get(route('users.show', 'privateorgperson'))->assertDontSee('Grace Chapel');
    }

    public function test_inactive_membership_is_not_exposed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'inactive-membership-org', 'public');
        $member = $this->createUserWithProfile(['name' => 'Pending Member Person'], ['username' => 'pendingmemberperson']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'pending']);

        $this->get(route('users.show', 'pendingmemberperson'))->assertDontSee('Grace Chapel');
    }

    // JOBS/SERVICES

    public function test_public_job_appears_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Job Poster Person'], ['username' => 'jobposterperson']);
        Job::create(['user_id' => $user->id, 'title' => 'Profile Visible Job', 'slug' => 'profile-visible-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('users.show', 'jobposterperson'))->assertSee('Profile Visible Job');
    }

    public function test_draft_job_does_not_appear_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Draft Job Person'], ['username' => 'draftjobperson']);
        Job::create(['user_id' => $user->id, 'title' => 'Profile Hidden Draft Job', 'slug' => 'profile-hidden-draft-job', 'status' => 'draft', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('users.show', 'draftjobperson'))->assertDontSee('Profile Hidden Draft Job');
    }

    public function test_private_job_does_not_appear_on_profile_for_outsider(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Private Job Person'], ['username' => 'privatejobperson']);
        Job::create(['user_id' => $user->id, 'title' => 'Profile Hidden Private Job', 'slug' => 'profile-hidden-private-job', 'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote']);

        $this->get(route('users.show', 'privatejobperson'))->assertDontSee('Profile Hidden Private Job');
    }

    public function test_closed_job_does_not_appear_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Closed Job Person'], ['username' => 'closedjobperson']);
        Job::create(['user_id' => $user->id, 'title' => 'Profile Hidden Closed Job', 'slug' => 'profile-hidden-closed-job', 'status' => 'closed', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->get(route('users.show', 'closedjobperson'))->assertDontSee('Profile Hidden Closed Job');
    }

    // RESOURCES / AUDIO

    public function test_public_resource_appears_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Resource Person'], ['username' => 'resourceperson']);
        LibraryResource::create(['user_id' => $user->id, 'type' => 'book', 'title' => 'Profile Visible Book', 'slug' => 'profile-visible-book', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('users.show', 'resourceperson'))->assertSee('Profile Visible Book');
    }

    public function test_private_resource_does_not_appear_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Private Resource Person'], ['username' => 'privateresourceperson']);
        LibraryResource::create(['user_id' => $user->id, 'type' => 'book', 'title' => 'Profile Hidden Book', 'slug' => 'profile-hidden-book', 'status' => 'published', 'visibility' => 'private']);

        $this->get(route('users.show', 'privateresourceperson'))->assertDontSee('Profile Hidden Book');
    }

    public function test_public_audio_appears_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Audio Person'], ['username' => 'audioperson']);
        AudioResource::create(['user_id' => $user->id, 'title' => 'Profile Visible Track', 'slug' => 'profile-visible-track', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('users.show', 'audioperson'))->assertSee('Profile Visible Track');
    }

    public function test_private_audio_does_not_appear_on_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Private Audio Person'], ['username' => 'privateaudioperson']);
        AudioResource::create(['user_id' => $user->id, 'title' => 'Profile Hidden Track', 'slug' => 'profile-hidden-track', 'status' => 'published', 'visibility' => 'private']);

        $this->get(route('users.show', 'privateaudioperson'))->assertDontSee('Profile Hidden Track');
    }

    // MESSAGING

    public function test_authenticated_visitor_sees_message_action(): void
    {
        $visitor = User::factory()->create();
        $this->createUserWithProfile(['name' => 'Messageable Person'], ['username' => 'messageableperson']);

        $this->actingAs($visitor)->get(route('users.show', 'messageableperson'))->assertSee('Message');
    }

    public function test_owner_does_not_see_message_action_on_their_own_profile(): void
    {
        $user = $this->createUserWithProfile(['name' => 'Self Message Person'], ['username' => 'selfmessageperson']);

        $response = $this->actingAs($user)->get(route('users.show', 'selfmessageperson'));
        $this->assertFalse($response->viewData('canMessage'));
    }

    public function test_messaging_from_profile_creates_a_real_conversation(): void
    {
        $visitor = User::factory()->create();
        $target = $this->createUserWithProfile(['name' => 'Conversation Target'], ['username' => 'conversationtarget']);

        $this->actingAs($visitor)->post(route('messages.start'), ['user_id' => $target->id])->assertRedirect();

        $this->assertDatabaseHas('conversations', ['direct_key' => \App\Models\Conversation::directKeyFor($visitor->id, $target->id)]);
    }

    // AUTHORIZATION / UNAUTHORIZED MODIFICATION

    public function test_visitor_cannot_modify_another_users_profile_via_settings(): void
    {
        $owner = $this->createUserWithProfile(['name' => 'Owner Person'], ['username' => 'ownerperson']);
        $attacker = User::factory()->create();

        // Settings routes are always scoped to the authenticated user — there
        // is no {user} route parameter to redirect the update toward someone
        // else's profile.
        $this->actingAs($attacker)->put(route('settings.privacy.update'), ['discoverable' => '0']);

        $this->assertTrue(app(\App\Services\PreferenceService::class)->isDiscoverable($owner->fresh()));
    }

    // CROSS-ORGANIZATION ISOLATION

    public function test_cross_organization_membership_does_not_leak_through_profile(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'cross-org-a', 'public');
        $ownerB = User::factory()->create();
        $memberOfB = $this->createUserWithProfile(['name' => 'Org B Member Person'], ['username' => 'orgbmemberperson']);
        $orgB = $this->createOrganization($ownerB, 'cross-org-b', 'private');
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $memberOfB->id, 'status' => 'active']);

        $response = $this->get(route('users.show', 'orgbmemberperson'));

        $response->assertDontSee('cross-org-b');
        $this->assertCount(0, $response->viewData('organizations'));
    }

    // SEO / METADATA

    public function test_public_profile_includes_meta_description(): void
    {
        $this->createUserWithProfile(['name' => 'SEO Person'], ['username' => 'seoperson', 'bio' => 'A description for search engines.']);

        $this->get(route('users.show', 'seoperson'))->assertSee('A description for search engines.', false);
    }

    public function test_public_profile_has_canonical_url(): void
    {
        $this->createUserWithProfile(['name' => 'Canonical Person'], ['username' => 'canonicalperson']);

        $this->get(route('users.show', 'canonicalperson'))
            ->assertSee('rel="canonical" href="'.route('users.show', 'canonicalperson').'"', false);
    }
}
