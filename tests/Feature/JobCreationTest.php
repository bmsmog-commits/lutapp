<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCreationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    public function test_user_can_create_a_personal_job(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'Need a logo designer', 'visibility' => 'private', 'work_mode' => 'remote',
        ]);

        $job = Job::where('title', 'Need a logo designer')->first();
        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame($user->id, $job->user_id);
        $this->assertNull($job->organization_id);
        $this->assertSame('draft', $job->status);
    }

    public function test_organization_admin_can_create_an_organization_job(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $response = $this->actingAs($owner)->post(route('organizations.jobs.store', $organization), [
            'title' => 'Need a website developer', 'visibility' => 'public', 'work_mode' => 'remote',
        ]);

        $job = Job::where('title', 'Need a website developer')->first();
        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame($organization->id, $job->organization_id);
        $this->assertNull($job->user_id);
    }

    public function test_a_plain_member_cannot_create_an_organization_job(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('organizations.jobs.store', $organization), [
            'title' => 'X', 'visibility' => 'public', 'work_mode' => 'remote',
        ])->assertForbidden();
    }

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), ['visibility' => 'private', 'work_mode' => 'remote'])
            ->assertSessionHasErrors('title');
    }

    public function test_category_must_be_one_of_the_known_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'X', 'visibility' => 'private', 'work_mode' => 'remote', 'category' => 'Not A Real Category',
        ])->assertSessionHasErrors('category');
    }

    public function test_work_mode_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'X', 'visibility' => 'private', 'work_mode' => 'teleportation',
        ])->assertSessionHasErrors('work_mode');
    }

    public function test_a_job_in_a_private_organization_is_forced_private_regardless_of_requested_visibility(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');

        $this->actingAs($owner)->post(route('organizations.jobs.store', $organization), [
            'title' => 'Internal role', 'visibility' => 'public', 'work_mode' => 'remote',
        ]);

        $job = Job::where('title', 'Internal role')->first();
        $this->assertSame('private', $job->visibility);
    }

    public function test_fixed_budget_sets_max_equal_to_min(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'Fixed price job', 'visibility' => 'private', 'work_mode' => 'remote',
            'budget_type' => 'fixed', 'budget_min' => 500, 'currency' => 'NGN',
        ]);

        $job = Job::where('title', 'Fixed price job')->first();
        $this->assertEquals(500, $job->budget_min);
        $this->assertEquals(500, $job->budget_max);
    }

    public function test_negotiable_budget_clears_min_and_max(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'Negotiable job', 'visibility' => 'private', 'work_mode' => 'remote',
            'budget_type' => 'negotiable', 'budget_min' => 500, 'budget_max' => 1000,
        ]);

        $job = Job::where('title', 'Negotiable job')->first();
        $this->assertNull($job->budget_min);
        $this->assertNull($job->budget_max);
    }

    public function test_budget_max_cannot_be_less_than_budget_min(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'Range job', 'visibility' => 'private', 'work_mode' => 'remote',
            'budget_type' => 'range', 'budget_min' => 1000, 'budget_max' => 500,
        ])->assertSessionHasErrors('budget_max');
    }

    public function test_slug_is_generated_and_unique(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('jobs.store'), ['title' => 'Same Title', 'visibility' => 'public', 'work_mode' => 'remote']);
        $this->actingAs($user)->post(route('jobs.store'), ['title' => 'Same Title', 'visibility' => 'public', 'work_mode' => 'remote']);

        $slugs = Job::where('title', 'Same Title')->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertNotEquals($slugs[0], $slugs[1]);
    }

    public function test_guest_cannot_create_a_job(): void
    {
        $this->post(route('jobs.store'), ['title' => 'X', 'visibility' => 'public', 'work_mode' => 'remote'])
            ->assertRedirect(route('login'));
    }

    public function test_a_restricted_user_cannot_create_a_job(): void
    {
        $user = User::factory()->create(['account_status' => 'restricted']);

        $this->actingAs($user)->post(route('jobs.store'), [
            'title' => 'X', 'visibility' => 'private', 'work_mode' => 'remote',
        ])->assertForbidden();

        $this->assertDatabaseMissing('jobs', ['title' => 'X']);
    }
}
