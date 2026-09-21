<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function createProfile(User $user): UserProfile
    {
        return UserProfile::create(['user_id' => $user->id, 'username' => 'user'.$user->id]);
    }

    public function test_user_can_upload_their_own_profile_photo(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->image('me.jpg')->size(50),
        ])->assertRedirect(route('profile.show'));

        $user->profile->refresh();
        $this->assertNotNull($user->profile->profilePhoto);
        Storage::disk('local')->assertExists($user->profile->profilePhoto->path);
    }

    public function test_user_can_replace_their_profile_photo(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('first.jpg')->size(30)]);
        $firstPhoto = $user->profile->refresh()->profilePhoto;

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('second.jpg')->size(30)]);
        $user->profile->refresh();

        $this->assertNotEquals($firstPhoto->id, $user->profile->profile_photo_media_id);
        $this->assertDatabaseMissing('media_files', ['id' => $firstPhoto->id]);
        Storage::disk('local')->assertMissing($firstPhoto->path);
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);
        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg')->size(30)]);
        $photo = $user->profile->refresh()->profilePhoto;

        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertRedirect(route('profile.show'));

        $this->assertNull($user->profile->refresh()->profile_photo_media_id);
        $this->assertDatabaseMissing('media_files', ['id' => $photo->id]);
    }

    public function test_a_user_cannot_modify_another_users_photo(): void
    {
        $owner = User::factory()->create();
        $this->createProfile($owner);
        $this->actingAs($owner)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg')->size(30)]);
        $photo = $owner->profile->refresh()->profilePhoto;

        $intruder = User::factory()->create();

        // There is no route to target another user's profile photo directly —
        // the endpoint always acts on the authenticated user's own profile —
        // so the isolation guarantee is that acting as the intruder never
        // touches the owner's photo, regardless of what the intruder submits.
        $this->actingAs($intruder)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('hijack.jpg')->size(30)]);

        $this->assertDatabaseHas('media_files', ['id' => $photo->id]);
        $this->assertSame($photo->id, $owner->profile->refresh()->profile_photo_media_id);
    }

    public function test_guest_cannot_upload_a_profile_photo(): void
    {
        $this->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('x.jpg')])
            ->assertRedirect(route('login'));
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($user->profile->refresh()->profile_photo_media_id);
    }

    public function test_executable_disguised_as_image_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->create('shell.php', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_oversized_image_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);
        $maxKb = config('media.max_size_kb.image');

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->image('big.jpg')->size($maxKb + 500),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($user->profile->refresh()->profile_photo_media_id);
    }

    public function test_previous_photo_remains_if_replacement_upload_is_invalid(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);
        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('good.jpg')->size(30)]);
        $originalPhoto = $user->profile->refresh()->profilePhoto;

        $this->actingAs($user)->post(route('profile.photo.store'), [
            'photo' => UploadedFile::fake()->create('bad.php', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('photo');

        $this->assertSame($originalPhoto->id, $user->profile->refresh()->profile_photo_media_id);
        Storage::disk('local')->assertExists($originalPhoto->path);
    }

    public function test_another_user_cannot_view_a_private_profile_photo_via_direct_url(): void
    {
        $owner = User::factory()->create();
        $this->createProfile($owner);
        $this->actingAs($owner)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('me.jpg')->size(30)]);
        $photo = $owner->profile->refresh()->profilePhoto;

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get(route('files.show', $photo))->assertForbidden();
    }

    public function test_direct_media_id_manipulation_cannot_bypass_photo_authorization(): void
    {
        $ownerA = User::factory()->create();
        $this->createProfile($ownerA);
        $this->actingAs($ownerA)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $photoA = $ownerA->profile->refresh()->profilePhoto;

        $ownerB = User::factory()->create();
        $this->createProfile($ownerB);

        // ownerB can view their own (nonexistent yet) photo route normally, but
        // substituting ownerA's media ID directly must still be denied.
        $this->actingAs($ownerB)->get(route('files.show', $photoA))->assertForbidden();
    }
}
