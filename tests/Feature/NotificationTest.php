<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Donation;
use App\Models\GivingCampaign;
use App\Models\GivingTransaction;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\NotificationService;
use App\Services\Payments\FakePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    // CRUD / SERVICE

    public function test_notification_service_creates_a_notification(): void
    {
        $recipient = User::factory()->create();
        $actor = User::factory()->create();

        $notification = app(NotificationService::class)->notify(
            recipient: $recipient, type: 'message.new', title: 'Hello', actor: $actor,
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $recipient->id, 'actor_id' => $actor->id, 'type' => 'message.new']);
        $this->assertNull($notification->read_at);
    }

    public function test_service_never_notifies_a_user_about_their_own_action(): void
    {
        $user = User::factory()->create();

        $notification = app(NotificationService::class)->notify(
            recipient: $user, type: 'message.new', title: 'Self', actor: $user,
        );

        $this->assertNull($notification);
        $this->assertSame(0, Notification::count());
    }

    public function test_user_can_retrieve_their_notifications_paginated(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 25) as $i) {
            Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => "Notification {$i}"]);
        }

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $this->assertCount(20, $response->viewData('notifications'));
        $this->assertTrue($response->viewData('notifications')->hasMorePages());
    }

    public function test_notifications_are_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Older', 'created_at' => now()->subDay()]);
        $newer = Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Newer', 'created_at' => now()]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $ids = $response->viewData('notifications')->pluck('id')->all();
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_mark_one_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Hi']);

        $this->actingAs($user)->post(route('notifications.read', $notification))->assertRedirect();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'A']);
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'B']);

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotificationsCount());
    }

    public function test_unread_count_reflects_only_unread_notifications(): void
    {
        $user = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Unread']);
        Notification::create(['user_id' => $user->id, 'type' => 'message.new', 'title' => 'Read', 'read_at' => now()]);

        $this->assertSame(1, $user->unreadNotificationsCount());
    }

    // AUTHORIZATION

    public function test_user_can_view_their_own_notifications_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
    }

    public function test_another_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $notification = Notification::create(['user_id' => $owner->id, 'type' => 'message.new', 'title' => 'Private']);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->post(route('notifications.read', $notification))->assertForbidden();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_notification_id_manipulation_cannot_reveal_or_modify_another_users_notification(): void
    {
        $victim = User::factory()->create();
        $notification = Notification::create(['user_id' => $victim->id, 'type' => 'message.new', 'title' => 'Secret content']);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->post(route('notifications.read', $notification->id))->assertForbidden();
    }

    // MESSAGING INTEGRATION

    public function test_new_message_creates_a_notification_for_the_recipient(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hello there']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $b->id, 'actor_id' => $a->id, 'type' => 'message.new']);
        $this->assertSame(0, Notification::where('user_id', $a->id)->count());
    }

    public function test_message_notification_links_to_the_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hi']);
        $notification = Notification::where('user_id', $b->id)->first();

        $this->assertSame(route('messages.show', $conversation), $notification->relatedUrl($b));
    }

    // JOB INTEGRATION

    public function test_job_application_notifies_the_job_owner(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Logo Design', 'slug' => 'logo-design', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'I can help.']);

        $this->assertDatabaseHas('app_notifications', ['user_id' => $owner->id, 'actor_id' => $applicant->id, 'type' => 'job.application.created']);
    }

    public function test_accepted_application_notifies_the_applicant(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Logo Design', 'slug' => 'logo-design-2', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.accept', [$job, $application]));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $applicant->id, 'type' => 'job.application.accepted']);
    }

    public function test_rejected_application_notifies_the_applicant(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Logo Design', 'slug' => 'logo-design-3', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.reject', [$job, $application]));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $applicant->id, 'type' => 'job.application.rejected']);
    }

    public function test_withdrawn_application_notifies_the_job_owner(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Logo Design', 'slug' => 'logo-design-4', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($applicant)->post(route('jobs.applications.withdraw', [$job, $application]));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $owner->id, 'actor_id' => $applicant->id, 'type' => 'job.application.withdrawn']);
    }

    public function test_job_application_notification_links_to_the_application(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Logo Design', 'slug' => 'logo-design-5', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();
        $notification = Notification::where('user_id', $owner->id)->first();

        $this->assertSame(route('jobs.applications.show', [$job, $application]), $notification->relatedUrl($owner));
    }

    // EVENT INTEGRATION

    public function test_rsvp_notifies_the_event_creator(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Sunday Service',
            'slug' => 'sunday-service', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $attendee = User::factory()->create();

        $this->actingAs($attendee)->post(route('org-events.rsvp.store', $event));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $owner->id, 'actor_id' => $attendee->id, 'type' => 'event.rsvp.new']);
    }

    public function test_duplicate_rsvp_does_not_create_a_second_notification(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Sunday Service',
            'slug' => 'sunday-service-2', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $attendee = User::factory()->create();
        $this->actingAs($attendee)->post(route('org-events.rsvp.store', $event));
        $this->actingAs($attendee)->delete(route('org-events.rsvp.destroy', $event));

        $this->actingAs($attendee)->post(route('org-events.rsvp.store', $event));

        $this->assertSame(2, Notification::where('user_id', $owner->id)->where('type', 'event.rsvp.new')->count());
        // Two genuine attending transitions (initial + re-RSVP after cancel) —
        // not three, which would mean the no-op path also notified.
    }

    public function test_event_cancellation_notifies_attendees(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Sunday Service',
            'slug' => 'sunday-service-3', 'status' => 'published', 'visibility' => 'public',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $attendee = User::factory()->create();
        $this->actingAs($attendee)->post(route('org-events.rsvp.store', $event));

        $this->actingAs($owner)->post(route('org-events.cancel', $event));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $attendee->id, 'type' => 'event.cancelled']);
    }

    public function test_unauthorized_user_cannot_reach_private_event_information_through_a_notification(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $event = OrganizationEvent::create([
            'organization_id' => $organization->id, 'creator_id' => $owner->id, 'title' => 'Private Meeting',
            'slug' => 'private-meeting', 'status' => 'published', 'visibility' => 'private',
            'starts_at' => now()->addDays(2), 'location_mode' => 'physical',
        ]);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member)->post(route('org-events.rsvp.store', $event));
        $notification = Notification::where('user_id', $owner->id)->first();

        $stranger = User::factory()->create();
        $this->assertNull($notification->relatedUrl($stranger));
    }

    // ORGANIZATION INTEGRATION

    public function test_added_member_receives_a_notification_with_organization_context(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newMember = User::factory()->create();

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $newMember->id]);

        $notification = Notification::where('user_id', $newMember->id)->where('type', 'organization.member.added')->first();
        $this->assertNotNull($notification);
        $this->assertSame($organization->id, $notification->organization_id);
    }

    public function test_removed_member_receives_a_notification(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $memberRow = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)->delete(route('organizations.members.destroy', [$organization, $memberRow]));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $member->id, 'type' => 'organization.member.removed']);
    }

    public function test_cross_organization_notifications_do_not_mix(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $userInBoth = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $userInBoth->id, 'status' => 'active']);

        $this->actingAs($ownerB)->post(route('organizations.members.store', $orgB), ['user_id' => $userInBoth->id]);

        $notification = Notification::where('user_id', $userInBoth->id)->where('type', 'organization.member.added')->first();
        $this->assertSame($orgB->id, $notification->organization_id);
        $this->assertNotSame($orgA->id, $notification->organization_id);
    }

    // GIVING INTEGRATION

    public function test_successful_donation_notifies_the_donor(): void
    {
        FakePaymentProvider::resetLedger();
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = GivingCampaign::create([
            'organization_id' => $organization->id, 'title' => 'Building Fund', 'slug' => 'building-fund',
            'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public',
        ]);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success']);

        $notification = Notification::where('user_id', $donor->id)->where('type', 'giving.donation.successful')->first();
        $this->assertNotNull($notification);
        $this->assertSame($organization->id, $notification->organization_id);
    }

    public function test_donation_notification_does_not_expose_sensitive_payment_data(): void
    {
        FakePaymentProvider::resetLedger();
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = GivingCampaign::create([
            'organization_id' => $organization->id, 'title' => 'Building Fund', 'slug' => 'building-fund-2',
            'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public',
        ]);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success']);

        $notification = Notification::where('user_id', $donor->id)->first();
        $this->assertStringNotContainsString($transaction->reference, $notification->title.$notification->body);
        $this->assertNull($notification->body);
    }

    public function test_guest_donation_creates_no_notification(): void
    {
        FakePaymentProvider::resetLedger();
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = GivingCampaign::create([
            'organization_id' => $organization->id, 'title' => 'Building Fund', 'slug' => 'building-fund-3',
            'currency' => 'NGN', 'status' => 'published', 'visibility' => 'public',
        ]);
        $this->post(route('giving.donate', [$organization, $campaign]), [
            'amount' => '10', 'donor_name' => 'Guest', 'donor_email' => 'guest@example.com',
        ]);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success']);

        $this->assertSame(0, Notification::count());
    }

    // RESOURCE SAFETY

    public function test_notification_for_a_deleted_related_resource_renders_safely(): void
    {
        $recipient = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $recipient->id, 'type' => 'message.new', 'title' => 'Old message',
            'related_type' => Notification::RELATED_CONVERSATION, 'related_id' => 999999,
        ]);

        $this->assertNull($notification->relatedUrl($recipient));
        $this->actingAs($recipient)->get(route('notifications.index'))->assertOk()->assertSee('Old message');
    }

    public function test_inaccessible_related_resource_does_not_leak_a_link(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $job = Job::create([
            'organization_id' => $organization->id, 'title' => 'Secret Role', 'slug' => 'secret-role',
            'status' => 'published', 'visibility' => 'private', 'work_mode' => 'remote',
        ]);
        $notification = Notification::create([
            'user_id' => User::factory()->create()->id, 'type' => 'job.application.created', 'title' => 'Notice',
            'related_type' => Notification::RELATED_JOB, 'related_id' => $job->id,
        ]);
        $outsider = User::factory()->create();

        $this->assertNull($notification->relatedUrl($outsider));
    }

    // PREFERENCES

    public function test_disabling_a_category_suppresses_that_notification(): void
    {
        $recipient = User::factory()->create();
        UserPreference::create(['user_id' => $recipient->id, 'notification_preferences' => ['messages' => false]]);
        $actor = User::factory()->create();

        $notification = app(NotificationService::class)->notify(
            recipient: $recipient, type: 'message.new', title: 'Hi', actor: $actor,
        );

        $this->assertNull($notification);
        $this->assertSame(0, Notification::count());
    }

    public function test_disabling_all_notifications_suppresses_every_category(): void
    {
        $recipient = User::factory()->create();
        UserPreference::create(['user_id' => $recipient->id, 'notifications_enabled' => false]);
        $actor = User::factory()->create();

        $notification = app(NotificationService::class)->notify(
            recipient: $recipient, type: 'job.application.created', title: 'Hi', actor: $actor,
        );

        $this->assertNull($notification);
    }

    public function test_existing_user_preferences_remain_functional(): void
    {
        $user = User::factory()->create();
        $preferences = UserPreference::create(['user_id' => $user->id, 'theme' => 'dark', 'language' => 'en']);

        $this->assertSame('dark', $preferences->refresh()->theme);
        $this->assertTrue($preferences->notifications_enabled);
    }
}
