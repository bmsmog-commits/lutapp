<?php

namespace Tests\Feature;

use App\Models\AudioResource;
use App\Models\BibleBook;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\Conversation;
use App\Models\Donation;
use App\Models\GivingCampaign;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\OrganizationMember;
use App\Models\Resource as LibraryResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    // ACCESS

    public function test_authenticated_user_can_access_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_existing_personal_widgets_remain_present(): void
    {
        $user = User::factory()->create();
        $user->notes()->create(['title' => 'Test Note', 'body' => 'Body']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('notesCount'));
    }

    // USER CONTEXT

    public function test_dashboard_shows_the_correct_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Dashboard Person']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Dashboard Person');
    }

    // NOTIFICATIONS

    public function test_unread_notification_count_is_correct(): void
    {
        $user = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Unread one']);
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Read one', 'read_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame(1, $response->viewData('notifications')['unreadCount']);
        $response->assertSee('Unread one');
    }

    public function test_only_recipients_own_notifications_appear(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Notification::create(['user_id' => $other->id, 'type' => 'message.new', 'title' => 'Not Mine Notification']);

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Not Mine Notification');
    }

    // MESSAGING

    public function test_recent_conversations_appear(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Chat Partner']);
        $conversation = Conversation::findOrCreateDirect($user, $other);
        $conversation->messages()->create(['sender_id' => $user->id, 'type' => 'text', 'body' => 'hi']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Chat Partner');
    }

    public function test_unrelated_conversation_is_excluded(): void
    {
        $user = User::factory()->create();
        $a = User::factory()->create(['name' => 'Person A']);
        $b = User::factory()->create(['name' => 'Person B']);
        Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Person A')->assertDontSee('Person B');
    }

    // ORGANIZATIONS

    public function test_users_organizations_appear_with_role(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'my-org-dash', 'public');

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertSee('Grace Chapel')->assertSee('Organization Owner');
    }

    public function test_unrelated_organization_is_excluded(): void
    {
        $owner = User::factory()->create();
        $this->createOrganization($owner, 'unrelated-org-dash', 'public');
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Grace Chapel');
    }

    // EVENTS

    public function test_upcoming_accessible_event_appears(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-dash-org', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Dashboard Visible Event',
            'slug' => 'dashboard-visible-event', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);

        $this->actingAs($owner)->get(route('dashboard'))->assertSee('Dashboard Visible Event');
    }

    public function test_private_event_excluded_from_dashboard(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-dash-org-2', 'public');
        OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Dashboard Private Event',
            'slug' => 'dashboard-private-event', 'status' => 'published', 'visibility' => 'private',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Dashboard Private Event');
    }

    public function test_rsvp_status_shown_when_attending(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'event-dash-org-3', 'public');
        $event = OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'RSVP Dashboard Event',
            'slug' => 'rsvp-dashboard-event', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $attendee = User::factory()->create();
        $event->rsvp($attendee);

        $this->actingAs($attendee)->get(route('dashboard'))->assertSee('Attending');
    }

    // JOBS

    public function test_discoverable_job_appears(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Dashboard Visible Job', 'slug' => 'dashboard-visible-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->actingAs($owner)->get(route('dashboard'))->assertSee('Dashboard Visible Job');
    }

    public function test_draft_job_excluded_from_dashboard(): void
    {
        $owner = User::factory()->create();
        Job::create(['user_id' => $owner->id, 'title' => 'Dashboard Draft Job', 'slug' => 'dashboard-draft-job', 'status' => 'draft', 'visibility' => 'public', 'work_mode' => 'remote']);

        $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('Dashboard Draft Job');
    }

    public function test_users_own_applications_are_scoped_correctly(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'App Scope Job', 'slug' => 'app-scope-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        JobApplication::create(['job_id' => $job->id, 'applicant_id' => $applicant->id, 'status' => 'pending']);

        $response = $this->actingAs($applicant)->get(route('dashboard'));
        $response->assertSee('recent application');

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('dashboard'))->assertDontSee('recent application');
    }

    // RESOURCES

    public function test_accessible_resource_appears(): void
    {
        $user = User::factory()->create();
        LibraryResource::create(['user_id' => $user->id, 'type' => 'book', 'title' => 'Dashboard Visible Book', 'slug' => 'dashboard-visible-book', 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Dashboard Visible Book');
    }

    public function test_private_resource_excluded_from_dashboard(): void
    {
        $owner = User::factory()->create();
        LibraryResource::create(['user_id' => $owner->id, 'type' => 'book', 'title' => 'Dashboard Private Book', 'slug' => 'dashboard-private-book', 'status' => 'published', 'visibility' => 'private']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Dashboard Private Book');
    }

    // AUDIO

    public function test_accessible_audio_appears(): void
    {
        $user = User::factory()->create();
        AudioResource::create(['user_id' => $user->id, 'title' => 'Dashboard Visible Song', 'slug' => 'dashboard-visible-song', 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('Dashboard Visible Song');
    }

    public function test_private_audio_excluded_from_dashboard(): void
    {
        $owner = User::factory()->create();
        AudioResource::create(['user_id' => $owner->id, 'title' => 'Dashboard Private Song', 'slug' => 'dashboard-private-song', 'status' => 'published', 'visibility' => 'private']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Dashboard Private Song');
    }

    // BIBLE

    public function test_users_recent_bible_activity_appears(): void
    {
        $user = User::factory()->create();
        $translation = BibleTranslation::create(['code' => 'KJVD', 'name' => 'KJV Dash', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $book = BibleBook::create(['name' => 'John', 'abbreviation' => 'Jn', 'testament' => 'new', 'sort_order' => 43, 'chapters_count' => 21]);
        BibleReadingHistory::create(['user_id' => $user->id, 'bible_book_id' => $book->id, 'chapter' => 3, 'translation_id' => $translation->id, 'last_read_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('John 3');
    }

    public function test_another_users_bible_activity_never_appears(): void
    {
        $owner = User::factory()->create();
        $translation = BibleTranslation::create(['code' => 'KJVD2', 'name' => 'KJV Dash 2', 'language' => 'English', 'is_active' => true, 'public_domain' => true]);
        $book = BibleBook::create(['name' => 'Mark', 'abbreviation' => 'Mk', 'testament' => 'new', 'sort_order' => 41, 'chapters_count' => 16]);
        BibleReadingHistory::create(['user_id' => $owner->id, 'bible_book_id' => $book->id, 'chapter' => 5, 'translation_id' => $translation->id, 'last_read_at' => now()]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Mark 5');
    }

    // GIVING

    public function test_users_own_donation_appears(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'giving-dash-org', 'public');
        $campaign = GivingCampaign::create(['organization_id' => $organization->id, 'title' => 'Dashboard Fund', 'slug' => 'dashboard-fund', 'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public']);
        $donor = User::factory()->create();
        Donation::create(['campaign_id' => $campaign->id, 'organization_id' => $organization->id, 'user_id' => $donor->id, 'amount' => 1000, 'currency' => 'NGN', 'reference' => 'DASH-REF-1', 'status' => 'successful']);

        $this->actingAs($donor)->get(route('dashboard'))->assertSee('Dashboard Fund');
    }

    public function test_another_users_donation_never_appears(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'giving-dash-org-2', 'public');
        $campaign = GivingCampaign::create(['organization_id' => $organization->id, 'title' => 'Hidden Dashboard Fund', 'slug' => 'hidden-dashboard-fund', 'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public']);
        $donor = User::factory()->create();
        Donation::create(['campaign_id' => $campaign->id, 'organization_id' => $organization->id, 'user_id' => $donor->id, 'amount' => 1000, 'currency' => 'NGN', 'reference' => 'DASH-REF-2', 'status' => 'successful']);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Hidden Dashboard Fund');
    }

    public function test_no_sensitive_payment_data_is_exposed_on_dashboard(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'giving-dash-org-3', 'public');
        $campaign = GivingCampaign::create(['organization_id' => $organization->id, 'title' => 'Safe Fund', 'slug' => 'safe-fund', 'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public']);
        $donor = User::factory()->create();
        Donation::create(['campaign_id' => $campaign->id, 'organization_id' => $organization->id, 'user_id' => $donor->id, 'amount' => 1000, 'currency' => 'NGN', 'reference' => 'DASH-REF-SECRET-3', 'status' => 'successful']);

        $this->actingAs($donor)->get(route('dashboard'))->assertDontSee('DASH-REF-SECRET-3');
    }

    // SEARCH

    public function test_dashboard_search_entry_points_to_unified_search(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee(route('search.index'), false);
    }

    // PRIVACY / ID MANIPULATION

    public function test_manipulated_organization_context_cannot_expose_private_data(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'manip-dash-org', 'private');
        Job::create(['organization_id' => $organization->id, 'title' => 'Manip Dashboard Job', 'slug' => 'manip-dashboard-job', 'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('dashboard'))->assertDontSee('Manip Dashboard Job');
    }

    // PERFORMANCE

    public function test_dashboard_does_not_issue_an_excessive_number_of_queries(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization($user, 'perf-org', 'public');
        for ($i = 0; $i < 8; $i++) {
            Job::create(['organization_id' => $organization->id, 'title' => "Perf Job {$i}", 'slug' => "perf-job-{$i}", 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'));
        $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // A generous ceiling, not a tight budget — the point is catching an
        // N+1 regression (which would scale with record count), not
        // micro-optimizing the exact number.
        $this->assertLessThan(60, $queryCount);
    }
}
