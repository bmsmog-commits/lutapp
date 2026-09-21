<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    private function makeResource(array $overrides = []): Resource
    {
        return Resource::create(array_merge([
            'title' => 'Test Resource', 'slug' => 'test-resource-'.uniqid(),
            'type' => 'book', 'status' => 'draft', 'visibility' => 'private',
        ], $overrides));
    }

    // MEDIA INTEGRATION

    public function test_uploading_a_cover_creates_media_through_media_storage_service(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('resources.cover.store', $resource), [
            'cover' => UploadedFile::fake()->image('cover.jpg')->size(50),
        ])->assertRedirect(route('resources.show', $resource));

        $resource->refresh();
        $this->assertDatabaseHas('media_files', ['id' => $resource->cover_media_id, 'user_id' => $user->id, 'category' => 'image']);
    }

    public function test_replacing_the_cover_removes_the_old_media(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('resources.cover.store', $resource), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $firstCover = $resource->refresh()->cover;

        $this->actingAs($user)->post(route('resources.cover.store', $resource), ['cover' => UploadedFile::fake()->image('b.jpg')->size(30)]);

        $this->assertDatabaseMissing('media_files', ['id' => $firstCover->id]);
    }

    public function test_deleting_a_resource_removes_its_media(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('resources.cover.store', $resource), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $cover = $resource->refresh()->cover;

        $this->actingAs($user)->delete(route('resources.destroy', $resource));

        $this->assertDatabaseMissing('media_files', ['id' => $cover->id]);
        $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
    }

    public function test_book_file_upload_accepts_pdf(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('resources.file.store', $resource), [
            'file' => UploadedFile::fake()->create('book.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('resources.show', $resource));

        $this->assertNotNull($resource->refresh()->file_media_id);
    }

    // SECURITY / ISOLATION

    public function test_non_owner_cannot_modify_a_personal_resource(): void
    {
        $owner = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('resources.update', $resource), ['title' => 'Hijacked', 'visibility' => 'private'])
            ->assertForbidden();
        $this->actingAs($intruder)->delete(route('resources.destroy', $resource))->assertForbidden();
    }

    public function test_unauthorized_organization_member_cannot_access_a_restricted_organization_resource(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $resource = $this->makeResource(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'private']);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('resources.show', $resource))->assertForbidden();
    }

    public function test_authorized_organization_member_can_access_a_private_organization_resource(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $resource = $this->makeResource(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'private']);

        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('resources.show', $resource))->assertOk();
    }

    public function test_organization_a_member_cannot_access_organization_bs_private_resource(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $resourceB = $this->makeResource(['organization_id' => $orgB->id, 'status' => 'published', 'visibility' => 'private']);

        $memberA = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $memberA->id, 'status' => 'active']);

        $this->actingAs($memberA)->get(route('resources.show', $resourceB))->assertForbidden();
    }

    public function test_new_organization_members_do_not_automatically_gain_access_to_unrelated_private_resources(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $resourceB = $this->makeResource(['organization_id' => $orgB->id, 'status' => 'published', 'visibility' => 'private']);

        $newMemberA = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $newMemberA->id, 'status' => 'active']);

        $this->actingAs($newMemberA)->get(route('resources.show', $resourceB))->assertForbidden();
    }

    public function test_public_resource_is_visible_to_a_guest(): void
    {
        $resource = $this->makeResource(['user_id' => User::factory()->create()->id, 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('resources.show', $resource))->assertOk();
    }

    public function test_draft_resource_is_not_visible_to_a_non_owner_even_if_marked_public(): void
    {
        $owner = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $owner->id, 'status' => 'draft', 'visibility' => 'public']);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('resources.show', $resource))->assertForbidden();
        $this->get(route('resources.show', $resource))->assertForbidden();
    }

    public function test_archived_resource_is_not_visible_to_a_non_owner(): void
    {
        $owner = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $owner->id, 'status' => 'archived', 'visibility' => 'public']);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('resources.show', $resource))->assertForbidden();
    }

    public function test_owner_can_still_view_their_own_draft_resource(): void
    {
        $owner = User::factory()->create();
        $resource = $this->makeResource(['user_id' => $owner->id, 'status' => 'draft', 'visibility' => 'private']);

        $this->actingAs($owner)->get(route('resources.show', $resource))->assertOk();
    }

    public function test_direct_id_manipulation_cannot_bypass_resource_authorization(): void
    {
        $ownerA = User::factory()->create();
        $resourceA = $this->makeResource(['user_id' => $ownerA->id, 'status' => 'published', 'visibility' => 'private']);

        $ownerB = User::factory()->create();
        $this->actingAs($ownerB)->get(route('resources.show', $resourceA->id))->assertForbidden();
        $this->actingAs($ownerB)->delete(route('resources.destroy', $resourceA->id))->assertForbidden();
    }

    public function test_publishing_a_private_organization_resource_still_keeps_underlying_file_private(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $resource = $this->makeResource(['organization_id' => $organization->id, 'status' => 'draft', 'visibility' => 'private']);
        $this->actingAs($owner)->post(route('resources.cover.store', $resource), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);

        $this->actingAs($owner)->post(route('resources.publish', $resource));

        $cover = $resource->refresh()->cover;
        $this->assertSame('private', $cover->visibility);
    }

    // SEARCH / FILTER

    public function test_library_search_matches_title(): void
    {
        $user = User::factory()->create();
        $this->makeResource(['user_id' => $user->id, 'title' => 'Findable Devotional', 'status' => 'published', 'visibility' => 'public']);
        $this->makeResource(['user_id' => $user->id, 'title' => 'Other Book', 'status' => 'published', 'visibility' => 'public']);

        $response = $this->get(route('resources.index', ['q' => 'Findable']));
        $response->assertOk()->assertSee('Findable Devotional')->assertDontSee('Other Book');
    }

    public function test_library_search_matches_author(): void
    {
        $user = User::factory()->create();
        $this->makeResource(['user_id' => $user->id, 'title' => 'Book One', 'author' => 'Unique Author Name', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('resources.index', ['q' => 'Unique Author Name']))->assertOk()->assertSee('Book One');
    }

    public function test_library_can_filter_by_category(): void
    {
        $user = User::factory()->create();
        $this->makeResource(['user_id' => $user->id, 'title' => 'Sermon Item', 'category' => 'Sermon', 'status' => 'published', 'visibility' => 'public']);
        $this->makeResource(['user_id' => $user->id, 'title' => 'Devotional Item', 'category' => 'Devotional', 'status' => 'published', 'visibility' => 'public']);

        $response = $this->get(route('resources.index', ['category' => 'Sermon']));
        $response->assertSee('Sermon Item')->assertDontSee('Devotional Item');
    }

    public function test_library_can_filter_by_language(): void
    {
        $user = User::factory()->create();
        $language = \App\Models\Language::create(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français']);
        $this->makeResource(['user_id' => $user->id, 'title' => 'French Book', 'language_id' => $language->id, 'status' => 'published', 'visibility' => 'public']);
        $this->makeResource(['user_id' => $user->id, 'title' => 'English Book', 'status' => 'published', 'visibility' => 'public']);

        $response = $this->get(route('resources.index', ['language_id' => $language->id]));
        $response->assertSee('French Book')->assertDontSee('English Book');
    }

    public function test_library_excludes_drafts_and_archived_from_default_listing(): void
    {
        $user = User::factory()->create();
        $this->makeResource(['user_id' => $user->id, 'title' => 'Draft Item', 'status' => 'draft', 'visibility' => 'public']);
        $this->makeResource(['user_id' => $user->id, 'title' => 'Published Item', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('resources.index'))->assertSee('Published Item')->assertDontSee('Draft Item');
    }

    // BOOKMARKS

    public function test_user_can_save_a_resource(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => User::factory()->create()->id, 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->post(route('resources.save', $resource))->assertRedirect();

        $this->assertDatabaseHas('saved_resources', ['user_id' => $user->id, 'resource_id' => $resource->id]);
    }

    public function test_user_can_unsave_a_resource(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => User::factory()->create()->id, 'status' => 'published', 'visibility' => 'public']);
        $user->savedResources()->attach($resource->id);

        $this->actingAs($user)->delete(route('resources.unsave', $resource))->assertRedirect();

        $this->assertDatabaseMissing('saved_resources', ['user_id' => $user->id, 'resource_id' => $resource->id]);
    }

    public function test_saving_the_same_resource_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $resource = $this->makeResource(['user_id' => User::factory()->create()->id, 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->post(route('resources.save', $resource));
        $this->actingAs($user)->post(route('resources.save', $resource));

        $this->assertSame(1, \DB::table('saved_resources')->where('user_id', $user->id)->where('resource_id', $resource->id)->count());
    }

    public function test_saved_resources_list_shows_only_the_users_own_saves(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $owner = User::factory()->create();
        $resourceA = $this->makeResource(['user_id' => $owner->id, 'title' => 'Mine', 'status' => 'published', 'visibility' => 'public']);
        $resourceB = $this->makeResource(['user_id' => $owner->id, 'title' => 'Not Mine', 'status' => 'published', 'visibility' => 'public']);

        $user->savedResources()->attach($resourceA->id);
        $other->savedResources()->attach($resourceB->id);

        $this->actingAs($user)->get(route('resources.saved'))->assertSee('Mine')->assertDontSee('Not Mine');
    }
}
