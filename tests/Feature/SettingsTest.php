<?php

namespace Tests\Feature;

use App\Models\AudioResource;
use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\OrganizationEvent;
use App\Models\Organization;
use App\Models\SecurityQuestion;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\PreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    // ACCESS

    public function test_authenticated_user_can_view_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    // ACCOUNT

    public function test_user_can_update_their_own_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => 'New Name', 'email' => 'new@example.com',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_changing_email_resets_verification_state(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name, 'email' => 'changed@example.com',
        ])->assertRedirect(route('verification.notice'));

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_email_uniqueness_is_enforced(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.account.update'), [
            'name' => $user->name, 'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');
    }

    // PASSWORD

    public function test_user_can_change_their_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('brand-new-password-456', $user->refresh()->password));
    }

    public function test_password_change_rejected_with_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password-456',
            'password_confirmation' => 'brand-new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('old-password-123', $user->refresh()->password));
    }

    // SECURITY QUESTION

    public function test_user_can_update_their_security_question(): void
    {
        $user = User::factory()->create();
        SecurityQuestion::create(['user_id' => $user->id, 'question' => 'Old Q', 'answer_hash' => Hash::make('old')]);

        $this->actingAs($user)->put(route('settings.security-question.update'), [
            'security_question' => 'What is your favorite color?', 'security_answer' => 'Blue',
        ])->assertRedirect();

        $question = $user->securityQuestions()->latest()->first();
        $this->assertSame('What is your favorite color?', $question->question);
        $this->assertTrue(Hash::check('blue', $question->answer_hash));
        $this->assertSame(1, $user->securityQuestions()->count());
    }

    // NOTIFICATIONS

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.notifications.update'), [
            'notifications_enabled' => '1', 'categories' => ['jobs', 'events'],
        ])->assertRedirect();

        $preference = $user->preferences()->first();
        $this->assertTrue($preference->notification_preferences['jobs']);
        $this->assertTrue($preference->notification_preferences['events']);
        $this->assertFalse($preference->notification_preferences['messages']);
    }

    public function test_disabling_a_notification_category_actually_suppresses_it(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($recipient)->put(route('settings.notifications.update'), [
            'notifications_enabled' => '1', 'categories' => ['jobs'],
        ]);

        $notification = app(\App\Services\NotificationService::class)->notify(
            recipient: $recipient->fresh(), type: 'message.new', title: 'Should be suppressed', actor: $actor,
        );

        $this->assertNull($notification);
    }

    public function test_disabling_all_notifications_suppresses_every_category(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $this->actingAs($recipient)->put(route('settings.notifications.update'), [
            'categories' => ['jobs', 'events', 'messages', 'organization', 'giving'],
        ]);

        $notification = app(\App\Services\NotificationService::class)->notify(
            recipient: $recipient->fresh(), type: 'job.application.created', title: 'Should be suppressed', actor: $actor,
        );

        $this->assertNull($notification);
    }

    public function test_user_cannot_submit_an_unknown_notification_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.notifications.update'), [
            'categories' => ['not-a-real-category'],
        ])->assertSessionHasErrors('categories.0');
    }

    // PRIVACY

    public function test_user_can_disable_discoverability(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.privacy.update'), [])->assertRedirect();

        $this->assertFalse(app(PreferenceService::class)->isDiscoverable($user->fresh()));
    }

    public function test_disabling_discoverability_hides_user_from_people_search(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Hidden Search Person']);
        $this->actingAs($target)->put(route('settings.privacy.update'), []);

        $this->actingAs($searcher)->get(route('search.index', ['q' => 'Hidden Search', 'type' => 'people']))
            ->assertDontSee('Hidden Search Person');
    }

    public function test_disabling_discoverability_hides_user_from_message_search_by_name(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Hidden Message Person']);
        $this->actingAs($target)->put(route('settings.privacy.update'), []);

        $this->actingAs($searcher)->get(route('messages.search', ['q' => 'Hidden Message']))
            ->assertDontSee('Hidden Message Person');
    }

    public function test_exact_email_lookup_still_works_even_when_not_discoverable(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Still Findable By Email', 'email' => 'findbyemail@example.com']);
        $this->actingAs($target)->put(route('settings.privacy.update'), []);

        $this->actingAs($searcher)->get(route('messages.search', ['q' => 'findbyemail@example.com']))
            ->assertSee('Still Findable By Email');
    }

    public function test_default_discoverability_preserves_existing_behavior(): void
    {
        $searcher = User::factory()->create();
        $target = User::factory()->create(['name' => 'Default Visible Person']);
        $target->profile()->create(['username' => 'defaultvisible', 'display_name' => 'Default Visible Person']);
        // No preference row at all — must behave exactly as before Phase 21.

        $response = $this->actingAs($searcher)->get(route('search.index', ['q' => 'Default Visible', 'type' => 'people']));

        // Asserting the actual result set, not just assertSee() — the search
        // box itself echoes the query text back into its value attribute,
        // which would make assertSee('Default Visible Person') pass even
        // with zero real results.
        $this->assertCount(1, $response->viewData('results'));
    }

    // APPEARANCE

    public function test_user_can_set_a_theme_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.appearance.update'), ['theme' => 'dark'])->assertRedirect();

        $this->assertSame('dark', $user->preferences()->first()->theme);
    }

    public function test_invalid_theme_value_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.appearance.update'), ['theme' => 'rainbow'])
            ->assertSessionHasErrors('theme');
    }

    public function test_default_theme_is_system(): void
    {
        $user = User::factory()->create();

        $this->assertSame('system', app(PreferenceService::class)->theme($user));
    }

    // BIBLE

    public function test_user_can_set_bible_preferences_without_altering_bible_data(): void
    {
        $user = User::factory()->create();
        $translation = BibleTranslation::create(['code' => 'KJVS', 'name' => 'KJV Settings', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);

        $this->actingAs($user)->put(route('settings.bible.update'), [
            'translation_id' => $translation->id, 'font_size' => 'large',
        ])->assertRedirect();

        $preference = app(PreferenceService::class)->bible($user->fresh());
        $this->assertSame($translation->id, $preference['translation_id']);
        $this->assertSame('large', $preference['font_size']);
    }

    public function test_bible_preference_sets_the_default_translation_on_the_reader(): void
    {
        $user = User::factory()->create();
        $translation = BibleTranslation::create(['code' => 'KJVS2', 'name' => 'KJV Settings 2', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $this->actingAs($user)->put(route('settings.bible.update'), ['translation_id' => $translation->id, 'font_size' => 'medium']);

        $response = $this->actingAs($user->fresh())->get(route('bible.index'));

        $response->assertOk();
        $this->assertSame($translation->id, $response->viewData('selectedTranslation')->id);
    }

    public function test_invalid_bible_font_size_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.bible.update'), ['font_size' => 'huge'])
            ->assertSessionHasErrors('font_size');
    }

    // AUDIO

    public function test_user_can_enable_audio_autoplay_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.audio.update'), ['autoplay' => '1'])->assertRedirect();

        $this->assertTrue(app(PreferenceService::class)->audio($user->fresh())['autoplay']);
    }

    public function test_audio_autoplay_preference_is_reflected_on_the_player(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('settings.audio.update'), ['autoplay' => '1']);
        $this->assertTrue($user->fresh()->preferences->audio_preferences['autoplay'] ?? false);

        $audio = AudioResource::create(['user_id' => $user->id, 'title' => 'Autoplay Track', 'slug' => 'autoplay-track', 'status' => 'published', 'visibility' => 'public']);

        // Re-authenticate with a freshly-loaded instance — Laravel's test
        // SessionGuard caches the resolved user for the rest of the test
        // once set, so re-using the original $user object here would read
        // back the preference state from before it was saved (a testing
        // artifact only; real requests each resolve the user fresh from
        // the session on their own process).
        $response = $this->actingAs($user->fresh())->get(route('audio.show', $audio));

        $response->assertOk();
        $this->assertTrue($response->viewData('canManage'), 'user() was not resolved on this request.');
        $this->assertTrue($response->viewData('autoplay'));
    }

    // DASHBOARD

    public function test_user_can_disable_a_dashboard_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.dashboard.update'), [
            'sections' => ['notifications', 'organizations'],
        ])->assertRedirect();

        $sections = app(PreferenceService::class)->dashboardSections($user->fresh());
        $this->assertSame(['notifications', 'organizations'], $sections);
    }

    public function test_disabled_dashboard_section_does_not_render(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('settings.dashboard.update'), ['sections' => ['organizations']]);

        $response = $this->actingAs($user->fresh())->get(route('dashboard'));

        $response->assertOk();
        $this->assertNull($response->viewData('giving'));
        $this->assertNotNull($response->viewData('organizations'));
    }

    public function test_default_dashboard_shows_all_sections(): void
    {
        $user = User::factory()->create();

        $sections = app(PreferenceService::class)->dashboardSections($user);

        $this->assertSame(PreferenceService::DASHBOARD_SECTIONS, $sections);
    }

    // CROSS-USER / AUTHORIZATION

    public function test_settings_updates_are_always_scoped_to_the_authenticated_user(): void
    {
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        $this->actingAs($userA)->put(route('settings.account.update'), ['name' => 'Hijacked Name', 'email' => $userA->email]);

        $this->assertSame('User B', $userB->fresh()->name);
    }

    public function test_notification_preferences_do_not_leak_across_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->actingAs($userA)->put(route('settings.notifications.update'), ['categories' => []]);

        $this->assertNull($userB->preferences);
    }

    // REGRESSION SPOT-CHECKS (existing systems keep working)

    public function test_existing_profile_photo_flow_still_works(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['username' => 'settingsuser', 'display_name' => 'Settings User']);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_existing_organization_features_still_work(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['owner_id' => $owner->id, 'name' => 'Settings Org', 'slug' => 'settings-org', 'type' => 'church', 'visibility' => 'public']);

        $this->actingAs($owner)->get(route('organizations.show', $organization))->assertOk();
    }

    public function test_existing_notifications_still_work(): void
    {
        $user = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Still Works']);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Still Works');
    }

    public function test_existing_dashboard_still_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
