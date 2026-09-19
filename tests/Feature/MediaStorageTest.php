<?php

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createOrganization(User $owner, string $slug = 'grace-chapel'): Organization
    {
        return Organization::create(['owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church']);
    }

    // STORAGE

    public function test_a_valid_image_can_be_stored_for_a_user(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 100, 100)->size(50);

        $media = (new MediaStorageService)->store($file, ['user_id' => $user->id]);

        $this->assertDatabaseHas('media_files', ['id' => $media->id, 'user_id' => $user->id, 'category' => 'image']);
        Storage::disk('local')->assertExists($media->path);
    }

    public function test_an_unlisted_mime_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $this->expectException(InvalidMediaFileException::class);
        (new MediaStorageService)->store($file, ['user_id' => $user->id]);
    }

    public function test_a_dangerous_executable_extension_is_rejected_even_with_an_image_mime(): void
    {
        $user = User::factory()->create();
        // Simulate a spoofed upload: a .php filename disguised with an image MIME type.
        $file = UploadedFile::fake()->create('shell.php', 10, 'image/jpeg');

        $this->expectException(InvalidMediaFileException::class);
        (new MediaStorageService)->store($file, ['user_id' => $user->id]);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('big.jpg')->size((5 * 1024 * 1024 / 1024) + 1024);

        $this->expectException(InvalidMediaFileException::class);
        (new MediaStorageService)->store($file, ['user_id' => $user->id]);
    }

    public function test_a_safe_generated_filename_is_used_instead_of_the_original(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('my original file name.jpg')->size(20);

        $media = (new MediaStorageService)->store($file, ['user_id' => $user->id]);

        $this->assertStringNotContainsString('my original file name', $media->path);
        $this->assertSame('my original file name.jpg', $media->original_name);
    }

    public function test_the_physical_storage_path_is_not_publicly_exposed_for_private_files(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('secret.jpg')->size(20);
        $media = (new MediaStorageService)->store($file, ['user_id' => $user->id], 'private');

        $this->assertSame('local', $media->disk);
        $this->assertStringStartsNotWith('public/', $media->path);
    }

    public function test_a_media_file_must_belong_to_exactly_one_owner_type(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization($user);
        $file = UploadedFile::fake()->image('x.jpg')->size(20);

        $this->expectException(\InvalidArgumentException::class);
        (new MediaStorageService)->store($file, ['user_id' => $user->id, 'organization_id' => $organization->id]);
    }

    // PERSONAL FILES

    public function test_owner_can_access_their_private_file(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $user->id]);

        $this->actingAs($user)->get(route('files.show', $media))->assertOk();
    }

    public function test_another_user_cannot_access_a_private_file(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('files.show', $media))->assertForbidden();
    }

    public function test_another_user_cannot_delete_a_private_file(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $owner->id]);

        $this->actingAs($intruder)->delete(route('files.destroy', $media))->assertForbidden();
        $this->assertDatabaseHas('media_files', ['id' => $media->id]);
    }

    public function test_guest_cannot_access_a_private_file_via_direct_url(): void
    {
        $owner = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $owner->id]);

        $this->get(route('files.show', $media))->assertNotFound();
    }

    // ORGANIZATION FILES

    public function test_an_authorized_organization_member_can_access_a_private_organization_file(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $media = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('doc.txt', 'Sample organization document content.'), ['organization_id' => $organization->id]);

        $this->actingAs($member)->get(route('files.show', $media))->assertOk();
    }

    public function test_a_different_organizations_member_cannot_access_a_private_organization_file(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $media = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('doc.txt', 'Sample organization document content.'), ['organization_id' => $orgA->id]);

        $this->actingAs($ownerB)->get(route('files.show', $media))->assertForbidden();
    }

    public function test_a_different_organizations_admin_cannot_delete_another_organizations_file(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $media = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('doc.txt', 'Sample organization document content.'), ['organization_id' => $orgA->id]);

        $this->actingAs($ownerB)->delete(route('files.destroy', $media))->assertForbidden();
        $this->assertDatabaseHas('media_files', ['id' => $media->id]);
    }

    public function test_organization_scoping_cannot_be_bypassed_by_manipulating_the_file_id(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $mediaA = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('a.txt', 'Document A content.'), ['organization_id' => $orgA->id]);
        $mediaB = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('b.txt', 'Document B content.'), ['organization_id' => $orgB->id]);

        // ownerA is authorized for orgA's own file, but trying mediaB's ID (a different
        // organization entirely) via the same route pattern must still be denied.
        $this->actingAs($ownerA)->get(route('files.show', $mediaA))->assertOk();
        $this->actingAs($ownerA)->get(route('files.show', $mediaB))->assertForbidden();
    }

    public function test_organization_owner_can_delete_their_organizations_file(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $media = (new MediaStorageService)->store(UploadedFile::fake()->createWithContent('doc.txt', 'Sample organization document content.'), ['organization_id' => $organization->id]);

        $this->actingAs($owner)->delete(route('files.destroy', $media))->assertRedirect();
        $this->assertDatabaseMissing('media_files', ['id' => $media->id]);
    }

    // PUBLIC FILES

    public function test_an_explicitly_public_file_can_be_accessed_by_a_guest(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('public.jpg')->size(20), ['user_id' => $user->id], 'public');

        $this->get(route('files.show', $media))->assertOk();
    }

    public function test_a_private_file_does_not_become_public_by_default(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('private.jpg')->size(20), ['user_id' => $user->id]);

        $this->assertSame('private', $media->visibility);
        $this->get(route('files.show', $media))->assertNotFound();
    }

    // DATABASE

    public function test_media_file_metadata_is_created_correctly(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.png')->size(30);

        $media = (new MediaStorageService)->store($file, ['user_id' => $user->id], 'private');

        $this->assertSame($user->id, $media->user_id);
        $this->assertNull($media->organization_id);
        $this->assertSame('image', $media->category);
        $this->assertSame('private', $media->visibility);
        $this->assertNotEmpty($media->checksum);
        $this->assertSame(30 * 1024, $media->size);
    }

    public function test_deleting_a_user_cascades_to_their_media_file_rows(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('media_files', ['id' => $media->id]);
    }

    public function test_deletion_handles_an_already_missing_physical_file_gracefully(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $user->id]);

        Storage::disk('local')->delete($media->path);

        (new MediaStorageService)->delete($media);

        $this->assertDatabaseMissing('media_files', ['id' => $media->id]);
    }

    // STORAGE ABSTRACTION

    public function test_storage_uses_the_configured_disk_rather_than_a_hardcoded_path(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $user->id], 'private');

        $this->assertContains($media->disk, ['local', 'public']);
        $this->assertStringNotContainsString(':\\', $media->path);
        $this->assertStringNotContainsString('/var/www', $media->path);
    }

    public function test_public_visibility_uses_the_public_disk(): void
    {
        $user = User::factory()->create();
        $media = (new MediaStorageService)->store(UploadedFile::fake()->image('a.jpg')->size(20), ['user_id' => $user->id], 'public');

        $this->assertSame('public', $media->disk);
    }
}
