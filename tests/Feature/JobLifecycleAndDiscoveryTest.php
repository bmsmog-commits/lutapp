<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobLifecycleAndDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeJob(array $overrides = []): Job
    {
        return Job::create(array_merge([
            'title' => 'Test Job', 'slug' => 'test-job-'.uniqid(),
            'status' => 'draft', 'visibility' => 'private', 'work_mode' => 'remote',
        ], $overrides));
    }

    // LIFECYCLE

    public function test_owner_can_publish_a_draft_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id]);

        $this->actingAs($owner)->post(route('jobs.publish', $job))->assertRedirect(route('jobs.show', $job));

        $this->assertSame('published', $job->refresh()->status);
    }

    public function test_owner_can_close_a_published_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'published']);

        $this->actingAs($owner)->post(route('jobs.close', $job));

        $this->assertSame('closed', $job->refresh()->status);
    }

    public function test_owner_can_cancel_a_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'published']);

        $this->actingAs($owner)->post(route('jobs.cancel', $job));

        $this->assertSame('cancelled', $job->refresh()->status);
    }

    public function test_non_owner_cannot_publish_close_or_cancel_a_job(): void
    {
        $owner = User::factory()->create();
        $job = $this->makeJob(['user_id' => $owner->id, 'status' => 'published']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('jobs.publish', $job))->assertForbidden();
        $this->actingAs($intruder)->post(route('jobs.close', $job))->assertForbidden();
        $this->actingAs($intruder)->post(route('jobs.cancel', $job))->assertForbidden();
    }

    // DISCOVERY

    public function test_published_public_job_appears_in_the_listing(): void
    {
        $this->makeJob(['user_id' => User::factory()->create()->id, 'title' => 'Visible Job', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('jobs.index'))->assertOk()->assertSee('Visible Job');
    }

    public function test_draft_job_does_not_appear_in_the_listing(): void
    {
        $this->makeJob(['user_id' => User::factory()->create()->id, 'title' => 'Draft Job', 'status' => 'draft', 'visibility' => 'public']);

        $this->get(route('jobs.index'))->assertDontSee('Draft Job');
    }

    public function test_private_job_does_not_leak_into_the_public_listing(): void
    {
        $owner = User::factory()->create();
        $this->makeJob(['user_id' => $owner->id, 'title' => 'Private Job', 'status' => 'published', 'visibility' => 'private']);

        $this->get(route('jobs.index'))->assertDontSee('Private Job');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('jobs.index'))->assertDontSee('Private Job');
    }

    public function test_search_matches_title_and_description(): void
    {
        $user = User::factory()->create();
        $this->makeJob(['user_id' => $user->id, 'title' => 'Findable Gig', 'status' => 'published', 'visibility' => 'public']);
        $this->makeJob(['user_id' => $user->id, 'title' => 'Other Gig', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('jobs.index', ['q' => 'Findable']))->assertSee('Findable Gig')->assertDontSee('Other Gig');
    }

    public function test_category_filter_works(): void
    {
        $user = User::factory()->create();
        $this->makeJob(['user_id' => $user->id, 'title' => 'Design Gig', 'category' => 'Graphic Design', 'status' => 'published', 'visibility' => 'public']);
        $this->makeJob(['user_id' => $user->id, 'title' => 'Writing Gig', 'category' => 'Writing', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('jobs.index', ['category' => 'Graphic Design']))->assertSee('Design Gig')->assertDontSee('Writing Gig');
    }

    public function test_work_mode_filter_works(): void
    {
        $user = User::factory()->create();
        $this->makeJob(['user_id' => $user->id, 'title' => 'Remote Gig', 'work_mode' => 'remote', 'status' => 'published', 'visibility' => 'public']);
        $this->makeJob(['user_id' => $user->id, 'title' => 'Onsite Gig', 'work_mode' => 'on_site', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('jobs.index', ['work_mode' => 'on_site']))->assertSee('Onsite Gig')->assertDontSee('Remote Gig');
    }

    public function test_location_filter_works(): void
    {
        $user = User::factory()->create();
        $this->makeJob(['user_id' => $user->id, 'title' => 'Lagos Gig', 'city' => 'Lagos', 'status' => 'published', 'visibility' => 'public']);
        $this->makeJob(['user_id' => $user->id, 'title' => 'Abuja Gig', 'city' => 'Abuja', 'status' => 'published', 'visibility' => 'public']);

        $this->get(route('jobs.index', ['city' => 'Lagos']))->assertSee('Lagos Gig')->assertDontSee('Abuja Gig');
    }
}
