<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationVisibilitySyncTest extends TestCase
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

    private function uploadLogo(User $actor, Organization $organization): void
    {
        $this->actingAs($actor)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png')->size(30),
        ]);
    }

    public function test_a_public_organizations_logo_is_publicly_accessible(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;

        $this->assertSame('public', $logo->visibility);
        $this->assertSame('public', $logo->disk);
        Storage::disk('public')->assertExists($logo->path);

        $this->get(route('files.show', $logo))->assertOk();
    }

    public function test_making_the_organization_private_revokes_public_access_to_its_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;
        $originalPath = $logo->path;

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'private',
        ]);

        $logo->refresh();
        $this->assertSame('private', $logo->visibility);
        $this->assertSame('local', $logo->disk);

        // The bytes must actually be gone from the public disk, not just the
        // database flag flipped — otherwise the old public URL would still work.
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('local')->assertExists($logo->path);

        $this->app['auth']->forgetGuards();
        $this->get(route('files.show', $logo))->assertNotFound();
    }

    public function test_an_authorized_member_can_still_access_the_logo_after_the_organization_becomes_private(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;

        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'private',
        ]);

        $this->actingAs($member)->get(route('files.show', $logo->fresh()))->assertOk();
    }

    public function test_making_the_organization_public_again_does_not_republish_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'private');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;

        $this->assertSame('private', $logo->visibility);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'public',
        ]);

        $logo->refresh();
        $this->assertSame('private', $logo->visibility, 'A private logo must never be silently republished.');

        $this->app['auth']->forgetGuards();
        $this->get(route('files.show', $logo))->assertNotFound();
    }

    public function test_a_new_logo_uploaded_after_becoming_private_is_stored_as_private(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'private',
        ]);

        $this->uploadLogo($owner, $organization->fresh());
        $logo = $organization->fresh()->logo;

        $this->assertSame('private', $logo->visibility);
    }

    public function test_cross_organization_access_remains_blocked_after_a_visibility_change(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a', 'public');
        $this->uploadLogo($ownerA, $orgA);
        $logoA = $orgA->refresh()->logo;

        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b', 'public');

        $this->actingAs($ownerA)->put(route('organizations.update', $orgA), [
            'name' => $orgA->name, 'slug' => $orgA->slug, 'type' => $orgA->type, 'visibility' => 'private',
        ]);

        // Direct media-ID manipulation from an unrelated organization's owner
        // must still be denied after the visibility change.
        $this->actingAs($ownerB)->get(route('files.show', $logoA->fresh()))->assertForbidden();
        $this->actingAs($ownerB)->delete(route('files.destroy', $logoA->fresh()))->assertForbidden();
    }

    public function test_updating_the_organization_without_changing_visibility_does_not_touch_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;
        $originalUpdatedAt = $logo->updated_at;

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => 'Grace Chapel Renamed', 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'public',
        ]);

        $logo->refresh();
        $this->assertSame('public', $logo->visibility);
        $this->assertSame('public', $logo->disk);
        $this->assertTrue($originalUpdatedAt->equalTo($logo->updated_at), 'Logo media row must not be rewritten when visibility does not change.');
    }

    public function test_existing_profile_photo_behavior_is_unaffected_by_organization_visibility_sync(): void
    {
        $user = User::factory()->create();
        \App\Models\UserProfile::create(['user_id' => $user->id, 'username' => 'photouser']);

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg')->size(30)])
            ->assertRedirect(route('profile.show'));

        $photo = $user->profile->refresh()->profilePhoto;
        $this->assertSame('private', $photo->visibility);
        $this->actingAs($user)->get(route('files.show', $photo))->assertOk();
    }

    public function test_direct_url_manipulation_of_organization_or_media_id_cannot_bypass_the_privacy_change(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->uploadLogo($owner, $organization);
        $logo = $organization->refresh()->logo;

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => $organization->type, 'visibility' => 'private',
        ]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('files.show', $logo->fresh()->id))->assertForbidden();
    }
}
