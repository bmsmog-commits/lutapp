<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church',
        ]);
    }

    public function test_authorized_user_can_view_departments(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->get(route('organizations.departments.index', $organization))->assertOk();
    }

    public function test_owner_can_create_a_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.departments.store', $organization), ['name' => 'Youth'])
            ->assertRedirect(route('organizations.departments.index', $organization));

        $this->assertDatabaseHas('departments', ['organization_id' => $organization->id, 'name' => 'Youth']);
    }

    public function test_a_plain_member_cannot_create_a_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('organizations.departments.store', $organization), ['name' => 'Youth'])
            ->assertForbidden();

        $this->assertDatabaseMissing('departments', ['organization_id' => $organization->id, 'name' => 'Youth']);
    }

    public function test_a_department_belongs_to_the_correct_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.departments.store', $organization), ['name' => 'Media']);

        $department = Department::where('name', 'Media')->first();
        $this->assertSame($organization->id, $department->organization_id);
    }

    public function test_cross_organization_department_access_is_blocked(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $departmentB = $orgB->departments()->create(['name' => 'Finance']);

        $this->actingAs($ownerA)->put(route('organizations.departments.update', [$orgA, $departmentB]), ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($ownerA)->delete(route('organizations.departments.destroy', [$orgA, $departmentB]))
            ->assertNotFound();
    }

    public function test_owner_can_update_a_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $department = $organization->departments()->create(['name' => 'Youth']);

        $this->actingAs($owner)->put(route('organizations.departments.update', [$organization, $department]), ['name' => 'Youth Ministry'])
            ->assertRedirect(route('organizations.departments.index', $organization));

        $this->assertSame('Youth Ministry', $department->refresh()->name);
    }

    public function test_a_plain_member_cannot_update_a_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $department = $organization->departments()->create(['name' => 'Youth']);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->put(route('organizations.departments.update', [$organization, $department]), ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_a_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $department = $organization->departments()->create(['name' => 'Youth']);

        $this->actingAs($owner)->delete(route('organizations.departments.destroy', [$organization, $department]))
            ->assertRedirect(route('organizations.departments.index', $organization));

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }
}
