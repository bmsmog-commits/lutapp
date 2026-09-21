<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Job;
use App\Models\ModerationAction;
use App\Models\ModerationNote;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithProfile(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create($userOverrides);
        $user->profile()->create(array_merge([
            'username' => 'user'.$user->id,
            'display_name' => $user->name,
        ], $profileOverrides));

        return $user->fresh();
    }

    private function moderator(): User
    {
        return User::factory()->create(['is_moderator' => true]);
    }

    // REPORTING

    public function test_authenticated_user_can_report_an_eligible_target(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', ['reporter_id' => $reporter->id, 'reportable_id' => $target->id, 'reason' => 'spam', 'status' => 'pending']);
    }

    public function test_unauthenticated_report_submission_is_blocked(): void
    {
        $target = $this->createUserWithProfile();

        $this->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Report::count());
    }

    public function test_valid_reasons_are_accepted(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();

        foreach (Report::REASONS as $reason) {
            $reporter = User::factory()->create();
            $this->actingAs($reporter)->post(route('report.store'), [
                'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => $reason,
            ])->assertRedirect();
        }

        $this->assertSame(count(Report::REASONS), Report::count());
    }

    public function test_invalid_reason_is_rejected(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'not-a-real-reason',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::count());
    }

    public function test_invalid_target_type_is_rejected(): void
    {
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => 'not-a-real-type', 'reportable_id' => 1, 'reason' => 'spam',
        ])->assertSessionHasErrors('reportable_type');
    }

    public function test_reporting_a_nonexistent_target_is_rejected(): void
    {
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => 999999, 'reason' => 'spam',
        ])->assertSessionHasErrors('report');

        $this->assertSame(0, Report::count());
    }

    public function test_oversized_description_is_rejected(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
            'description' => str_repeat('a', 3001),
        ])->assertSessionHasErrors('description');
    }

    public function test_duplicate_open_report_is_rejected(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
        ]);

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'harassment',
        ])->assertSessionHasErrors('report');

        $this->assertSame(1, Report::count());
    }

    public function test_a_new_report_is_allowed_once_the_prior_one_is_resolved(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $mod = $this->moderator();
        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
        ]);
        $report = Report::first();
        app(ModerationService::class)->updateStatus($report, $mod, 'resolved');

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'harassment',
        ])->assertRedirect();

        $this->assertSame(2, Report::count());
    }

    public function test_user_cannot_report_themselves(): void
    {
        $user = $this->createUserWithProfile();

        $this->actingAs($user)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $user->id, 'reason' => 'spam',
        ])->assertSessionHasErrors('report');
    }

    public function test_content_can_be_reported(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Reportable Job', 'slug' => 'reportable-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_JOB, 'reportable_id' => $job->id, 'reason' => 'scam_fraud',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', ['reportable_type' => Report::TARGET_JOB, 'reportable_id' => $job->id]);
    }

    public function test_report_submission_is_rate_limited(): void
    {
        $reporter = User::factory()->create();
        $targets = collect(range(1, 11))->map(fn ($i) => $this->createUserWithProfile([], ['username' => "reportratelimit{$i}"]));

        $lastResponse = null;
        foreach ($targets as $target) {
            $lastResponse = $this->actingAs($reporter)->post(route('report.store'), [
                'reportable_type' => Report::TARGET_USER, 'reportable_id' => $target->id, 'reason' => 'spam',
            ]);
        }

        $lastResponse->assertStatus(429);
    }

    // MODERATION DASHBOARD / AUTHORIZATION

    public function test_moderator_can_access_the_moderation_dashboard(): void
    {
        $mod = $this->moderator();

        $this->actingAs($mod)->get(route('admin.moderation.index'))->assertOk();
    }

    public function test_ordinary_user_is_denied_the_moderation_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.moderation.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_moderation_dashboard(): void
    {
        $this->get(route('admin.moderation.index'))->assertRedirect(route('login'));
    }

    public function test_organization_owner_cannot_access_platform_moderation(): void
    {
        $owner = User::factory()->create();
        Organization::create(['owner_id' => $owner->id, 'name' => 'Not A Moderator Org', 'slug' => 'not-a-moderator-org', 'type' => 'church', 'visibility' => 'public']);

        $this->actingAs($owner)->get(route('admin.moderation.index'))->assertForbidden();
    }

    public function test_organization_admin_cannot_access_platform_moderation(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['owner_id' => $owner->id, 'name' => 'Admin Org', 'slug' => 'admin-org', 'type' => 'church', 'visibility' => 'public']);
        $admin = User::factory()->create();
        \App\Models\OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $this->actingAs($admin)->get(route('admin.moderation.index'))->assertForbidden();
    }

    public function test_moderator_can_view_report_detail(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', 'desc');

        $this->actingAs($mod)->get(route('admin.moderation.reports.show', $report))->assertOk();
    }

    public function test_moderator_can_assign_a_reviewer(): void
    {
        $mod = $this->moderator();
        $reviewer = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.assign', $report), ['reviewer_id' => $reviewer->id])->assertRedirect();

        $this->assertSame($reviewer->id, $report->fresh()->assigned_to);
        $this->assertSame('under_review', $report->fresh()->status);
    }

    public function test_moderator_can_change_report_status(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.status', $report), ['status' => 'under_review'])->assertRedirect();

        $this->assertSame('under_review', $report->fresh()->status);
    }

    public function test_moderator_can_resolve_a_report(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.status', $report), ['status' => 'resolved', 'resolution' => 'Handled.'])->assertRedirect();

        $report->refresh();
        $this->assertSame('resolved', $report->status);
        $this->assertSame('Handled.', $report->resolution);
        $this->assertNotNull($report->resolved_at);
    }

    public function test_moderator_can_dismiss_a_report(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.status', $report), ['status' => 'dismissed'])->assertRedirect();

        $this->assertSame('dismissed', $report->fresh()->status);
    }

    public function test_ordinary_user_cannot_change_report_status(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->post(route('admin.moderation.reports.status', $report), ['status' => 'resolved'])->assertForbidden();

        $this->assertSame('pending', $report->fresh()->status);
    }

    public function test_moderation_action_audit_record_is_created(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.status', $report), ['status' => 'resolved']);

        $this->assertDatabaseHas('moderation_actions', [
            'moderator_id' => $mod->id, 'report_id' => $report->id, 'action' => 'report.status_changed',
            'previous_state' => 'pending', 'new_state' => 'resolved',
        ]);
    }

    // INTERNAL NOTES

    public function test_moderator_can_add_an_internal_note(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);

        $this->actingAs($mod)->post(route('admin.moderation.reports.notes.store', $report), ['note' => 'Investigating.'])->assertRedirect();

        $this->assertDatabaseHas('moderation_notes', ['report_id' => $report->id, 'moderator_id' => $mod->id, 'note' => 'Investigating.']);
    }

    public function test_internal_notes_are_not_accessible_to_ordinary_users(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);
        app(ModerationService::class)->addNote($report, $mod, 'Secret internal note.');

        // Neither the reporter nor the reported user has any route that can
        // reach this — the only read path is the moderator-gated report
        // detail page.
        $this->actingAs($reporter)->get(route('admin.moderation.reports.show', $report))->assertForbidden();
        $this->actingAs($target)->get(route('admin.moderation.reports.show', $report))->assertForbidden();
    }

    public function test_internal_notes_are_never_rendered_on_the_public_profile(): void
    {
        $mod = $this->moderator();
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', null);
        app(ModerationService::class)->addNote($report, $mod, 'Secret internal note content xyz.');

        $this->get(route('users.show', $target->profile->username))->assertDontSee('Secret internal note content xyz.');
    }

    // USER RESTRICTION / SUSPENSION

    public function test_moderator_can_warn_a_user(): void
    {
        $mod = $this->moderator();
        $target = $this->createUserWithProfile();

        $this->actingAs($mod)->post(route('admin.moderation.users.warn', $target), ['reason' => 'Be nice.'])->assertRedirect();

        $this->assertDatabaseHas('moderation_actions', ['target_type' => 'user', 'target_id' => $target->id, 'action' => 'user.warned']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $target->id, 'type' => 'moderation.warned']);
    }

    public function test_moderator_can_suspend_a_user(): void
    {
        $mod = $this->moderator();
        $target = $this->createUserWithProfile();

        $this->actingAs($mod)->post(route('admin.moderation.users.status', $target), ['status' => 'suspended', 'reason' => 'Policy violation.'])->assertRedirect();

        $this->assertSame('suspended', $target->fresh()->account_status);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $target = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('password123')]);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $this->post(route('login'), ['email' => $target->email, 'password' => 'password123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_user_is_logged_out_of_an_active_session(): void
    {
        $target = User::factory()->create();
        $mod = $this->moderator();

        $this->actingAs($target)->get(route('dashboard'))->assertOk();

        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_users_profile_is_hidden_from_the_public(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'hiddensuspended']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('users.show', 'hiddensuspended'))->assertNotFound();
    }

    public function test_suspended_user_excluded_from_search(): void
    {
        $target = $this->createUserWithProfile(['name' => 'Suspended Search Person'], ['username' => 'suspendedsearchperson']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $response = $this->get(route('search.index', ['q' => 'Suspended Search Person', 'type' => 'people']));

        $this->assertCount(0, $response->viewData('results'));
    }

    public function test_suspended_user_cannot_be_followed(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'suspendedfollow']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');
        $follower = User::factory()->create();

        $this->actingAs($follower)->post(route('users.follow', 'suspendedfollow'))->assertNotFound();
    }

    public function test_suspended_user_cannot_be_messaged(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'suspendedmsg']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');
        $sender = User::factory()->create();

        $this->actingAs($sender)->post(route('messages.start'), ['user_id' => $target->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_restricted_user_cannot_initiate_a_new_follow(): void
    {
        $restricted = $this->createUserWithProfile([], ['username' => 'restrictedfollower']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $restricted, 'restricted', 'test');
        $someoneElse = $this->createUserWithProfile([], ['username' => 'someoneelse']);

        $this->actingAs($restricted)->post(route('users.follow', 'someoneelse'))->assertSessionHasErrors('follow');
    }

    public function test_restricted_users_own_profile_remains_publicly_visible(): void
    {
        $restricted = $this->createUserWithProfile([], ['username' => 'stillvisiblerestricted']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $restricted, 'restricted', 'test');

        $this->get(route('users.show', 'stillvisiblerestricted'))->assertOk();
    }

    public function test_others_can_still_follow_a_restricted_user(): void
    {
        $restricted = $this->createUserWithProfile([], ['username' => 'followablerestricted']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $restricted, 'restricted', 'test');
        $follower = User::factory()->create();

        $this->actingAs($follower)->post(route('users.follow', 'followablerestricted'))->assertRedirect();

        $this->assertDatabaseHas('user_connections', ['follower_id' => $follower->id, 'following_id' => $restricted->id]);
    }

    public function test_reactivating_a_user_restores_normal_behavior(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'reactivateme']);
        $mod = $this->moderator();
        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $this->actingAs($mod)->post(route('admin.moderation.users.status', $target), ['status' => 'active'])->assertRedirect();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('users.show', 'reactivateme'))->assertOk();
    }

    public function test_suspending_a_user_does_not_delete_their_data(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'datapreserved']);
        $mod = $this->moderator();

        app(ModerationService::class)->setAccountStatus($mod, $target, 'suspended', 'test');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('user_profiles', ['user_id' => $target->id, 'username' => 'datapreserved']);
    }

    public function test_personal_job_excluded_from_discovery_when_owner_suspended(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Owner Suspended Job', 'slug' => 'owner-suspended-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $mod = $this->moderator();

        app(ModerationService::class)->setAccountStatus($mod, $owner, 'suspended', 'test');

        // Not assertDontSee() — the search form itself echoes the query text
        // back into its own input value="", which would make that assertion
        // pass or fail for the wrong reason. Check the actual result set.
        $response = $this->get(route('jobs.index', ['q' => 'Owner Suspended Job']));
        $this->assertCount(0, $response->viewData('jobs'));
    }

    // CONTENT MODERATION

    public function test_moderator_can_hide_reported_content(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'To Be Hidden Job', 'slug' => 'to-be-hidden-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $mod = $this->moderator();

        $this->actingAs($mod)->post(route('admin.moderation.content.hide'), [
            'target_type' => Report::TARGET_JOB, 'target_id' => $job->id, 'reason' => 'Scam listing.',
        ])->assertRedirect();

        $this->assertSame('private', $job->fresh()->visibility);
    }

    public function test_hidden_content_disappears_from_public_discovery(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Discovery Hidden Job', 'slug' => 'discovery-hidden-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $mod = $this->moderator();
        $this->actingAs($mod)->post(route('admin.moderation.content.hide'), ['target_type' => Report::TARGET_JOB, 'target_id' => $job->id]);

        $jobsResponse = $this->get(route('jobs.index', ['q' => 'Discovery Hidden Job']));
        $this->assertCount(0, $jobsResponse->viewData('jobs'));

        $searchResponse = $this->get(route('search.index', ['q' => 'Discovery Hidden Job', 'type' => 'jobs']));
        $this->assertCount(0, $searchResponse->viewData('results'));
    }

    public function test_hidden_content_direct_access_denied_for_outsider(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Direct Access Hidden Job', 'slug' => 'direct-access-hidden-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $mod = $this->moderator();
        $this->actingAs($mod)->post(route('admin.moderation.content.hide'), ['target_type' => Report::TARGET_JOB, 'target_id' => $job->id]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('jobs.show', $job))->assertForbidden();
    }

    public function test_content_ownership_remains_intact_after_hiding(): void
    {
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'Ownership Kept Job', 'slug' => 'ownership-kept-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $mod = $this->moderator();
        $this->actingAs($mod)->post(route('admin.moderation.content.hide'), ['target_type' => Report::TARGET_JOB, 'target_id' => $job->id]);

        $this->assertSame($owner->id, $job->fresh()->user_id);
        $this->actingAs($owner)->get(route('jobs.show', $job))->assertOk();
    }

    // PRIVACY / CROSS-USER / CROSS-ORGANIZATION

    public function test_users_cannot_see_other_users_reports(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile();
        app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', 'private description');
        $unrelated = User::factory()->create();

        $this->actingAs($unrelated)->get(route('admin.moderation.index'))->assertForbidden();
    }

    public function test_report_data_does_not_enter_unified_search(): void
    {
        $reporter = User::factory()->create();
        $target = $this->createUserWithProfile(['name' => 'Report Search Target'], ['username' => 'reportsearchtarget']);
        app(ModerationService::class)->submitReport($reporter, Report::TARGET_USER, $target->id, 'spam', 'UNIQUEREPORTDESCRIPTIONTEXT12345');

        $response = $this->get(route('search.index', ['q' => 'UNIQUEREPORTDESCRIPTIONTEXT12345']));

        // Not assertDontSee() — the search box echoes the query text back
        // into its own input value="". Check the actual result set is empty
        // across every provider instead.
        $response->assertOk();
        $grouped = $response->viewData('grouped');
        $totalResults = collect($grouped)->sum(fn ($items) => $items->count());
        $this->assertSame(0, $totalResults);
    }

    public function test_cross_organization_admin_cannot_reach_moderation_of_another_organization(): void
    {
        $ownerA = User::factory()->create();
        Organization::create(['owner_id' => $ownerA->id, 'name' => 'Org A Moderation', 'slug' => 'org-a-moderation', 'type' => 'church', 'visibility' => 'public']);
        $ownerB = User::factory()->create();
        $orgB = Organization::create(['owner_id' => $ownerB->id, 'name' => 'Org B Moderation', 'slug' => 'org-b-moderation', 'type' => 'church', 'visibility' => 'public']);
        $reporter = User::factory()->create();
        $report = app(ModerationService::class)->submitReport($reporter, Report::TARGET_ORGANIZATION, $orgB->id, 'spam', null);

        // Org A's owner has zero platform moderation authority, regardless of
        // being a legitimate owner of a completely unrelated organization.
        $this->actingAs($ownerA)->get(route('admin.moderation.reports.show', $report))->assertForbidden();
    }

    // BLOCKING REGRESSION (Phase 23)

    public function test_blocked_interaction_restrictions_remain_intact(): void
    {
        $blocker = $this->createUserWithProfile([], ['username' => 'blockertarget2']);
        $blocked = $this->createUserWithProfile([], ['username' => 'stillblocked']);
        $this->actingAs($blocker)->post(route('users.block', 'stillblocked'));

        $this->actingAs($blocked)->post(route('users.follow', 'blockertarget2'))
            ->assertSessionHasErrors('follow');
    }

    public function test_reporting_does_not_remove_an_existing_block(): void
    {
        $blocker = User::factory()->create();
        $blocked = $this->createUserWithProfile([], ['username' => 'reportkeepsblock']);
        $this->actingAs($blocker)->post(route('users.block', 'reportkeepsblock'));

        $this->actingAs($blocker)->post(route('report.store'), [
            'reportable_type' => Report::TARGET_USER, 'reportable_id' => $blocked->id, 'reason' => 'harassment',
        ]);

        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);
    }

    public function test_moderation_does_not_delete_conversations(): void
    {
        $a = User::factory()->create();
        $b = $this->createUserWithProfile([], ['username' => 'moderationkeepsconvo']);
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $conversation->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'kept message']);
        $mod = $this->moderator();

        app(ModerationService::class)->warn($mod, $b, 'test warning');

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'kept message']);
    }

    public function test_legitimate_system_generated_conversations_remain_functional(): void
    {
        // Phase 23's documented deferred behavior: block/restriction
        // enforcement covers user-initiated messaging entry points
        // (ConversationController::start, MessageController::store) but NOT
        // automated business flows like job-application acceptance, which
        // still creates a conversation via Conversation::findOrCreateDirect()
        // directly. Phase 24 does not change this — centralizing the check
        // into findOrCreateDirect() itself would risk silently breaking
        // legitimate flows (job acceptance, giving contact) that aren't user-
        // initiated messaging and aren't in scope for this foundation. This
        // test documents that the flow still works exactly as before.
        $owner = User::factory()->create();
        $job = Job::create(['user_id' => $owner->id, 'title' => 'System Flow Job', 'slug' => 'system-flow-job', 'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote']);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = \App\Models\JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.accept', [$job, $application]))->assertRedirect();

        $this->assertNotNull($application->fresh()->conversation_id);
    }
}
