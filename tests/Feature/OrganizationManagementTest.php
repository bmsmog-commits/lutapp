<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, array $overrides = []): Organization
    {
        return Organization::create(array_merge([
            'owner_id' => $owner->id,
            'name' => 'Grace Chapel',
            'slug' => 'grace-chapel',
            'type' => 'church',
            'visibility' => 'public',
            'status' => 'active',
        ], $overrides));
    }

    public function test_authenticated_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'ABC Technologies',
            'slug' => 'abc-technologies',
            'type' => 'company',
        ]);

        $organization = Organization::where('slug', 'abc-technologies')->first();
        $response->assertRedirect(route('organizations.show', $organization));
        $this->assertSame($user->id, $organization->owner_id);
    }

    public function test_guest_cannot_create_an_organization(): void
    {
        $this->post(route('organizations.store'), ['name' => 'X', 'slug' => 'x', 'type' => 'company'])
            ->assertRedirect(route('login'));
    }

    public function test_organization_slug_must_be_unique_via_the_form(): void
    {
        $this->createOrganization(User::factory()->create());
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Another Org',
            'slug' => 'grace-chapel',
            'type' => 'church',
        ])->assertSessionHasErrors('slug');
    }

    public function test_creator_becomes_organization_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'ABC Technologies', 'slug' => 'abc-technologies', 'type' => 'company',
        ]);

        $organization = Organization::where('slug', 'abc-technologies')->first();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue($user->refresh()->hasRole('Organization Owner'));
    }

    public function test_created_organization_appears_in_the_creators_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'ABC Technologies', 'slug' => 'abc-technologies', 'type' => 'company',
        ]);

        $this->actingAs($user)->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('ABC Technologies');
    }

    public function test_active_member_can_view_a_private_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, ['visibility' => 'private']);

        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('organizations.show', $organization))->assertOk();
    }

    public function test_unauthorized_user_cannot_access_a_private_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, ['visibility' => 'private']);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('organizations.show', $organization))
            ->assertForbidden();
    }

    public function test_owner_can_update_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => 'Grace Chapel Updated',
            'slug' => $organization->slug,
            'type' => 'church',
        ])->assertRedirect(route('organizations.show', $organization));

        $this->assertSame('Grace Chapel Updated', $organization->refresh()->name);
    }

    public function test_unauthorized_user_cannot_update_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->put(route('organizations.update', $organization), [
            'name' => 'Hijacked', 'slug' => $organization->slug, 'type' => 'church',
        ])->assertForbidden();

        $this->assertSame('Grace Chapel', $organization->refresh()->name);
    }

    public function test_owner_can_delete_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->delete(route('organizations.destroy', $organization))
            ->assertRedirect(route('organizations.index'));

        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_a_plain_member_cannot_delete_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->delete(route('organizations.destroy', $organization))->assertForbidden();
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_a_member_cannot_promote_themselves_via_the_member_update_endpoint(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $membership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->put(route('organizations.members.update', [$organization, $membership]), [
            'role' => 'Organization Admin', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_the_member_update_endpoint_never_accepts_an_owner_role_assignment(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $membership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $this->actingAs($owner)->put(route('organizations.members.update', [$organization, $membership]), [
            'role' => 'Organization Owner', 'status' => 'active',
        ])->assertSessionHasErrors('role');
    }

    public function test_a_user_from_organization_a_cannot_access_organization_b_management_routes(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, ['slug' => 'org-a']);

        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, ['slug' => 'org-b', 'visibility' => 'private']);

        $this->actingAs($ownerA)->get(route('organizations.show', $orgB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('organizations.members.index', $orgB))->assertForbidden();
        $this->actingAs($ownerA)->get(route('organizations.departments.index', $orgB))->assertForbidden();
        $this->actingAs($ownerA)->put(route('organizations.update', $orgB), ['name' => 'x', 'slug' => 'org-b', 'type' => 'church'])->assertForbidden();
    }
}
