<?php

namespace Tests\Feature;

use App\Models\AudioResource;
use App\Models\Language;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioResourceTest extends TestCase
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

    private function makeAudio(array $overrides = []): AudioResource
    {
        return AudioResource::create(array_merge([
            'title' => 'Test Audio', 'slug' => 'test-audio-'.uniqid(),
            'status' => 'draft', 'visibility' => 'private',
        ], $overrides));
    }

    // CRUD

    public function test_user_can_create_personal_audio(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('audio.store'), [
            'title' => 'My Worship Track', 'visibility' => 'private',
        ]);

        $audio = AudioResource::where('title', 'My Worship Track')->first();
        $response->assertRedirect(route('audio.show', $audio));
        $this->assertSame($user->id, $audio->user_id);
        $this->assertNull($audio->organization_id);
        $this->assertSame('draft', $audio->status);
    }

    public function test_organization_admin_can_create_organization_audio(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $response = $this->actingAs($admin)->post(route('organizations.audio.store', $organization), [
            'title' => 'Sunday Sermon', 'visibility' => 'public',
        ]);

        $audio = AudioResource::where('title', 'Sunday Sermon')->first();
        $response->assertRedirect(route('audio.show', $audio));
        $this->assertSame($organization->id, $audio->organization_id);
    }

    public function test_plain_member_cannot_create_organization_audio(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('organizations.audio.store', $organization), ['title' => 'X', 'visibility' => 'public'])
            ->assertForbidden();
    }

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('audio.store'), ['visibility' => 'private'])
            ->assertSessionHasErrors('title');
    }

    public function test_a_restricted_user_cannot_create_audio(): void
    {
        $user = User::factory()->create(['account_status' => 'restricted']);

        $this->actingAs($user)->post(route('audio.store'), [
            'title' => 'X', 'visibility' => 'private',
        ])->assertForbidden();

        $this->assertDatabaseMissing('audio_resources', ['title' => 'X']);
    }

    public function test_owner_can_update_audio(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->put(route('audio.update', $audio), ['title' => 'Renamed', 'visibility' => 'private'])
            ->assertRedirect();

        $this->assertSame('Renamed', $audio->refresh()->title);
    }

    public function test_owner_can_delete_audio(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('audio.destroy', $audio))->assertRedirect();

        $this->assertDatabaseMissing('audio_resources', ['id' => $audio->id]);
    }

    public function test_owner_can_publish_and_archive(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.publish', $audio));
        $this->assertSame('published', $audio->refresh()->status);

        $this->actingAs($user)->post(route('audio.archive', $audio));
        $this->assertSame('archived', $audio->refresh()->status);
    }

    // AUTHORIZATION

    public function test_non_owner_cannot_update_personal_audio(): void
    {
        $owner = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('audio.update', $audio), ['title' => 'Hijacked', 'visibility' => 'private'])
            ->assertForbidden();
    }

    public function test_organization_owner_and_admin_can_manage_but_member_cannot(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $audio = $this->makeAudio(['organization_id' => $organization->id]);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)->put(route('audio.update', $audio), ['title' => 'By Owner', 'visibility' => 'private'])->assertRedirect();
        $this->actingAs($member)->put(route('audio.update', $audio), ['title' => 'By Member', 'visibility' => 'private'])->assertForbidden();
    }

    public function test_cross_organization_denial(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $audioB = $this->makeAudio(['organization_id' => $orgB->id]);

        $this->actingAs($ownerA)->put(route('audio.update', $audioB), ['title' => 'Hijacked', 'visibility' => 'private'])
            ->assertForbidden();
    }

    // VISIBILITY

    public function test_public_audio_of_public_org_is_visible_to_guest(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'public', 'title' => 'Public Track']);

        $this->get(route('audio.show', $audio))->assertOk()->assertSee('Public Track');
    }

    public function test_private_audio_is_not_accessible_to_outsiders(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'private']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('audio.show', $audio))->assertForbidden();
        $this->get(route('audio.show', $audio))->assertForbidden();
    }

    public function test_organization_only_audio_visible_to_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'private']);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('audio.show', $audio))->assertOk();
    }

    public function test_public_org_going_private_hides_previously_public_audio(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'public']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('audio.show', $audio))->assertOk();

        $organization->update(['visibility' => 'private']);

        $this->actingAs($stranger)->get(route('audio.show', $audio))->assertForbidden();
    }

    public function test_private_org_going_public_does_not_auto_publish_previously_private_audio_media(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'private');
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'published', 'visibility' => 'private']);
        $this->actingAs($owner)->post(route('audio.file.store', $audio), [
            'audio' => UploadedFile::fake()->create('track.mp3', 500, 'audio/mpeg'),
        ]);
        $audio->refresh();
        $this->assertSame('private', $audio->audioMedia->visibility);

        $organization->update(['visibility' => 'public']);

        $this->assertSame('private', $audio->audioMedia->refresh()->visibility);
        $this->assertSame('private', $audio->refresh()->visibility);
    }

    // MEDIA

    public function test_valid_audio_upload_succeeds(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.file.store', $audio), [
            'audio' => UploadedFile::fake()->create('track.mp3', 1000, 'audio/mpeg'),
        ])->assertRedirect(route('audio.show', $audio));

        $audio->refresh();
        $this->assertDatabaseHas('media_files', ['id' => $audio->audio_media_id, 'category' => 'audio']);
    }

    public function test_invalid_mime_is_rejected(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.file.store', $audio), [
            'audio' => UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
        ])->assertSessionHasErrors('audio');

        $this->assertNull($audio->refresh()->audio_media_id);
    }

    public function test_invalid_extension_is_rejected(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.file.store', $audio), [
            'audio' => UploadedFile::fake()->create('track.exe', 100, 'audio/mpeg'),
        ])->assertSessionHasErrors('audio');
    }

    public function test_oversized_audio_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);
        $maxKb = config('media.max_size_kb.audio');

        $this->actingAs($user)->post(route('audio.file.store', $audio), [
            'audio' => UploadedFile::fake()->create('track.mp3', $maxKb + 1024, 'audio/mpeg'),
        ])->assertSessionHasErrors('audio');
    }

    public function test_replacing_audio_file_removes_the_old_media(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')]);
        $first = $audio->refresh()->audioMedia;

        $this->actingAs($user)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('b.mp3', 500, 'audio/mpeg')]);

        $this->assertDatabaseMissing('media_files', ['id' => $first->id]);
    }

    public function test_cover_upload_replace_and_removal(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.cover.store', $audio), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $first = $audio->refresh()->cover;
        $this->assertNotNull($first);

        $this->actingAs($user)->post(route('audio.cover.store', $audio), ['cover' => UploadedFile::fake()->image('b.jpg')->size(30)]);
        $this->assertDatabaseMissing('media_files', ['id' => $first->id]);

        $second = $audio->refresh()->cover;
        $this->actingAs($user)->delete(route('audio.cover.destroy', $audio));
        $this->assertDatabaseMissing('media_files', ['id' => $second->id]);
        $this->assertNull($audio->refresh()->cover_media_id);
    }

    public function test_deleting_audio_cleans_up_its_media(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')]);
        $this->actingAs($user)->post(route('audio.cover.store', $audio), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $audio->refresh();
        $audioMediaId = $audio->audio_media_id;
        $coverMediaId = $audio->cover_media_id;

        $this->actingAs($user)->delete(route('audio.destroy', $audio));

        $this->assertDatabaseMissing('media_files', ['id' => $audioMediaId]);
        $this->assertDatabaseMissing('media_files', ['id' => $coverMediaId]);
    }

    // DISCOVERY

    public function test_search_matches_title_and_creator(): void
    {
        $user = User::factory()->create();
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Findable Hymn', 'status' => 'published', 'visibility' => 'public']);
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Other Hymn', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('audio.index', ['q' => 'Findable']))->assertSee('Findable Hymn')->assertDontSee('Other Hymn');
    }

    public function test_category_filter_works(): void
    {
        $user = User::factory()->create();
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Sermon One', 'category' => 'Sermon', 'status' => 'published', 'visibility' => 'public']);
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Worship One', 'category' => 'Worship', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('audio.index', ['category' => 'Sermon']))->assertSee('Sermon One')->assertDontSee('Worship One');
    }

    public function test_language_filter_works(): void
    {
        $user = User::factory()->create();
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá']);
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Yoruba Track', 'language_id' => $language->id, 'status' => 'published', 'visibility' => 'public']);
        $this->makeAudio(['user_id' => $user->id, 'title' => 'English Track', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('audio.index', ['language_id' => $language->id]))->assertSee('Yoruba Track')->assertDontSee('English Track');
    }

    public function test_organization_filter_works(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $this->makeAudio(['organization_id' => $orgA->id, 'title' => 'Org A Track', 'status' => 'published', 'visibility' => 'public']);
        $this->makeAudio(['organization_id' => $orgB->id, 'title' => 'Org B Track', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('audio.index', ['organization_id' => $orgA->id]))->assertSee('Org A Track')->assertDontSee('Org B Track');
    }

    public function test_private_resources_excluded_from_public_discovery(): void
    {
        $user = User::factory()->create();
        $this->makeAudio(['user_id' => $user->id, 'title' => 'Hidden Track', 'status' => 'published', 'visibility' => 'private']);

        $this->get(route('audio.index'))->assertDontSee('Hidden Track');
    }

    public function test_my_audio_scope_shows_only_own_audio_regardless_of_status(): void
    {
        $user = User::factory()->create();
        $this->makeAudio(['user_id' => $user->id, 'title' => 'My Draft', 'status' => 'draft', 'visibility' => 'private']);
        $other = User::factory()->create();
        $this->makeAudio(['user_id' => $other->id, 'title' => 'Not Mine', 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->get(route('audio.index', ['scope' => 'mine']))
            ->assertSee('My Draft')->assertDontSee('Not Mine');
    }

    // PLAYBACK

    public function test_authorized_user_can_play_audio(): void
    {
        $user = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $user->id]);
        $this->actingAs($user)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')]);
        $audio->refresh();

        $this->actingAs($user)->get(route('files.show', $audio->audio_media_id))->assertOk();
    }

    public function test_unauthorized_user_cannot_play_private_audio(): void
    {
        $owner = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')]);
        $audio->refresh();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('files.show', $audio->audio_media_id))->assertForbidden();
    }

    public function test_guest_cannot_play_private_audio(): void
    {
        $owner = User::factory()->create();
        $audio = $this->makeAudio(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('audio.file.store', $audio), ['audio' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')]);
        $audio->refresh();
        Auth::logout();

        $this->get(route('files.show', $audio->audio_media_id))->assertNotFound();
    }

    public function test_archived_audio_is_not_publicly_playable_via_the_page(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $audio = $this->makeAudio(['organization_id' => $organization->id, 'status' => 'archived', 'visibility' => 'public']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('audio.show', $audio))->assertForbidden();
    }
}
