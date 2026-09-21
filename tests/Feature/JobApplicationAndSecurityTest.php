<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JobApplicationAndSecurityTest extends TestCase
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

    private function makeJob(array $overrides = []): Job
    {
        return Job::create(array_merge([
            'title' => 'Test Job', 'slug' => 'test-job-'.uniqid(),
            'status' => 'published', 'visibility' => 'public', 'work_mode' => 'remote',
        ], $overrides));
    }

    // APPLICATIONS

    public function test_a_user_can_apply_to_a_published_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'I can help.'])
            ->assertRedirect(route('jobs.show', $job));

        $application = JobApplication::first();
        $this->assertSame($applicant->id, $application->applicant_id);
        $this->assertSame('pending', $application->status);
    }

    public function test_duplicate_active_application_is_prevented(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'First']);
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Second'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1, JobApplication::count());
    }

    public function test_owner_cannot_apply_to_their_own_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('jobs.applications.store', $job), ['message' => 'Hi'])->assertForbidden();
        $this->assertSame(0, JobApplication::count());
    }

    public function test_applications_are_rejected_once_the_job_is_closed(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'closed']);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi'])->assertForbidden();
        $this->assertSame(0, JobApplication::count());
    }

    public function test_applications_are_rejected_once_the_job_has_expired(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'application_deadline' => now()->subDay()->toDateString()]);
        $applicant = User::factory()->create();

        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi'])->assertForbidden();
        $this->assertSame(0, JobApplication::count());
    }

    public function test_applicant_can_withdraw_their_own_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($applicant)->post(route('jobs.applications.withdraw', [$job, $application]))->assertRedirect();

        $this->assertSame('withdrawn', $application->refresh()->status);
    }

    public function test_owner_can_accept_an_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.accept', [$job, $application]))->assertRedirect();

        $this->assertSame('accepted', $application->refresh()->status);
    }

    public function test_owner_can_reject_an_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.reject', [$job, $application]))->assertRedirect();

        $this->assertSame('rejected', $application->refresh()->status);
    }

    public function test_applicant_cannot_accept_or_reject_their_own_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($applicant)->post(route('jobs.applications.accept', [$job, $application]))->assertForbidden();
        $this->actingAs($applicant)->post(route('jobs.applications.reject', [$job, $application]))->assertForbidden();
    }

    public function test_unrelated_user_cannot_view_another_users_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('jobs.applications.show', [$job, $application]))->assertForbidden();
    }

    public function test_a_user_cannot_manipulate_another_users_application_by_guessing_its_id(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $attacker = User::factory()->create();
        $this->actingAs($attacker)->post(route('jobs.applications.withdraw', [$job, $application]))->assertForbidden();
        $this->assertSame('pending', $application->refresh()->status);
    }

    // JOB ACCESS AUTHORIZATION

    public function test_owner_can_view_and_manage_own_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'draft', 'visibility' => 'private']);

        $this->actingAs($owner)->get(route('jobs.show', $job))->assertOk();
        $this->actingAs($owner)->put(route('jobs.update', $job), [
            'title' => 'Updated', 'visibility' => 'private', 'work_mode' => 'remote',
        ])->assertRedirect();
    }

    public function test_non_owner_cannot_edit_a_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('jobs.update', $job), [
            'title' => 'Hijacked', 'visibility' => 'private', 'work_mode' => 'remote',
        ])->assertForbidden();
    }

    public function test_private_job_cannot_be_viewed_by_an_unauthorized_user(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'visibility' => 'private']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('jobs.show', $job))->assertForbidden();
    }

    // ORGANIZATION ISOLATION

    public function test_organization_a_member_cannot_manage_organization_bs_job(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $memberA = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $memberA->id, 'status' => 'active']);

        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $jobB = $this->makeJob(['organization_id' => $orgB->id, 'visibility' => 'private']);

        $this->actingAs($memberA)->get(route('jobs.show', $jobB))->assertForbidden();
        $this->actingAs($memberA)->put(route('jobs.update', $jobB), [
            'title' => 'Hijacked', 'visibility' => 'private', 'work_mode' => 'remote',
        ])->assertForbidden();
    }

    public function test_organization_a_member_cannot_view_organization_bs_applications(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $memberA = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $memberA->id, 'status' => 'active']);

        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $jobB = $this->makeJob(['organization_id' => $orgB->id, 'visibility' => 'public']);

        $this->actingAs($memberA)->get(route('jobs.applications.index', $jobB))->assertForbidden();
    }

    public function test_a_user_does_not_gain_access_merely_by_knowing_an_organization_id(): void
    {
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b', 'private');
        $jobB = $this->makeJob(['organization_id' => $orgB->id, 'visibility' => 'private']);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('jobs.show', $jobB))->assertForbidden();
    }

    // ATTACHMENTS

    public function test_attachment_upload_uses_media_storage_service(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('jobs.attachment.store', $job), [
            'attachment' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('jobs.show', $job));

        $job->refresh();
        $this->assertDatabaseHas('media_files', ['id' => $job->attachment_media_id, 'user_id' => $owner->id]);
    }

    public function test_unauthorized_user_cannot_access_a_private_job_attachment(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'draft', 'visibility' => 'private']);
        $this->actingAs($owner)->post(route('jobs.attachment.store', $job), [
            'attachment' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ]);
        $job->refresh();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('files.show', $job->attachment_media_id))->assertForbidden();
    }

    public function test_guest_cannot_access_a_private_job_attachment(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'draft', 'visibility' => 'private']);
        $this->actingAs($owner)->post(route('jobs.attachment.store', $job), [
            'attachment' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ]);
        $job->refresh();
        Auth::logout();

        $this->get(route('files.show', $job->attachment_media_id))->assertNotFound();
    }

    public function test_deleting_a_job_removes_its_attachment(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $this->actingAs($owner)->post(route('jobs.attachment.store', $job), [
            'attachment' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ]);
        $job->refresh();
        $mediaId = $job->attachment_media_id;

        $this->actingAs($owner)->delete(route('jobs.destroy', $job));

        $this->assertDatabaseMissing('media_files', ['id' => $mediaId]);
    }

    // MESSAGING INTEGRATION

    public function test_accepting_an_application_creates_a_conversation_between_owner_and_applicant(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();

        $this->actingAs($owner)->post(route('jobs.applications.accept', [$job, $application]));

        $application->refresh();
        $this->assertNotNull($application->conversation_id);
        $conversation = Conversation::find($application->conversation_id);
        $this->assertTrue($conversation->participants->pluck('user_id')->contains($owner->id));
        $this->assertTrue($conversation->participants->pluck('user_id')->contains($applicant->id));

        $this->actingAs($applicant)->get(route('messages.show', $conversation))->assertOk();
        $this->actingAs($owner)->get(route('messages.show', $conversation))->assertOk();
    }

    public function test_unrelated_user_cannot_access_the_conversation_created_by_an_accepted_application(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);
        $applicant = User::factory()->create();
        $this->actingAs($applicant)->post(route('jobs.applications.store', $job), ['message' => 'Hi']);
        $application = JobApplication::first();
        $this->actingAs($owner)->post(route('jobs.applications.accept', [$job, $application]));
        $conversation = Conversation::find($application->refresh()->conversation_id);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
    }

    public function test_a_user_cannot_exploit_a_job_or_application_id_to_reach_an_unrelated_conversation(): void
    {
        $ownerA = User::factory()->create();
        $jobA = $this->makeJob(['user_id' => $ownerA->id]);
        $applicantA = User::factory()->create();
        $this->actingAs($applicantA)->post(route('jobs.applications.store', $jobA), ['message' => 'Hi']);
        $applicationA = JobApplication::first();
        $this->actingAs($ownerA)->post(route('jobs.applications.accept', [$jobA, $applicationA]));
        $conversationId = $applicationA->refresh()->conversation_id;

        $randomUser = User::factory()->create();
        $this->actingAs($randomUser)->get(route('messages.show', $conversationId))->assertForbidden();
    }
}
