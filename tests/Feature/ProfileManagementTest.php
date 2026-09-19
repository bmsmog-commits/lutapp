<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))->assertOk();
    }

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'username' => 'gabriel',
            'display_name' => 'Gabriel',
        ])->assertRedirect(route('profile.show'));

        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'username' => 'gabriel']);
    }

    public function test_username_uniqueness_is_enforced_across_users(): void
    {
        $existing = User::factory()->create();
        UserProfile::create(['user_id' => $existing->id, 'username' => 'taken']);

        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), ['username' => 'taken'])
            ->assertSessionHasErrors('username');
    }

    public function test_user_can_resubmit_their_own_existing_username_without_error(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'username' => 'gabriel']);

        $this->actingAs($user)->put(route('profile.update'), ['username' => 'gabriel'])
            ->assertSessionDoesntHaveErrors('username');
    }

    public function test_guest_cannot_view_or_update_profile(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->put(route('profile.update'), ['username' => 'x'])->assertRedirect(route('login'));
    }

    public function test_user_can_change_their_preferred_language(): void
    {
        $user = User::factory()->create();
        Language::create(['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá', 'is_active' => true]);

        $this->actingAs($user)->put(route('profile.update'), [
            'username' => 'gabriel',
            'language' => 'yo',
        ])->assertRedirect(route('profile.show'));

        $this->assertSame('yo', $user->preferences()->first()->language);
    }

    public function test_an_inactive_language_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        Language::create(['code' => 'xx', 'name' => 'Inactive Lang', 'native_name' => 'Inactive', 'is_active' => false]);

        $this->actingAs($user)->put(route('profile.update'), [
            'username' => 'gabriel',
            'language' => 'xx',
        ])->assertSessionHasErrors('language');
    }
}
