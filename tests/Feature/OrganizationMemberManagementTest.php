<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church',
        ]);
    }

    public function test_owner_can_view_the_member_list(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->get(route('organizations.members.index', $organization))->assertOk();
    }

    public function test_an_unauthorized_outsider_cannot_view_a_private_organizations_member_list(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $organization->update(['visibility' => 'private']);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('organizations.members.index', $organization))->assertForbidden();
    }

    public function test_admin_can_update_a_members_role_and_department(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $membership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)->put(route('organizations.members.update', [$organization, $membership]), [
            'role' => 'Organization Admin', 'status' => 'active',
        ])->assertRedirect(route('organizations.members.index', $organization));

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue($member->refresh()->hasRole('Organization Admin'));
    }

    public function test_role_assignment_is_scoped_to_the_organization_and_does_not_leak(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $member = User::factory()->create();
        $membershipA = OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $member->id, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($ownerA)->put(route('organizations.members.update', [$orgA, $membershipA]), [
            'role' => 'Organization Admin', 'status' => 'active',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $this->assertTrue(User::find($member->id)->hasRole('Organization Admin'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $this->assertFalse(User::find($member->id)->hasRole('Organization Admin'));
    }

    public function test_a_member_cannot_manage_another_organizations_members(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $memberB = OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => User::factory()->create()->id, 'status' => 'active']);

        $this->actingAs($ownerA)->put(route('organizations.members.update', [$orgA, $memberB]), [
            'role' => 'Member', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_the_sole_owner_cannot_be_removed_from_their_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $ownerMembership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'status' => 'active']);

        $this->actingAs($owner)->delete(route('organizations.members.destroy', [$organization, $ownerMembership]))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_members', ['id' => $ownerMembership->id]);
    }

    public function test_admin_can_remove_a_regular_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $membership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => User::factory()->create()->id, 'status' => 'active']);

        $this->actingAs($owner)->delete(route('organizations.members.destroy', [$organization, $membership]))
            ->assertRedirect(route('organizations.members.index', $organization));

        $this->assertDatabaseMissing('organization_members', ['id' => $membership->id]);
    }

    public function test_a_plain_member_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $memberA->id, 'status' => 'active']);
        $membershipB = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $memberB->id, 'status' => 'active']);

        $this->actingAs($memberA)->delete(route('organizations.members.destroy', [$organization, $membershipB]))
            ->assertForbidden();
    }
}
