<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationMemberDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church',
        ]);
    }

    // AUTHORIZATION

    public function test_guest_cannot_access_add_member_page(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->get(route('organizations.members.create', $organization))->assertRedirect(route('login'));
    }

    public function test_a_plain_member_cannot_access_add_member_page(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('organizations.members.create', $organization))->assertForbidden();
        $this->actingAs($member)->post(route('organizations.members.store', $organization), ['user_id' => User::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_organization_admin_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $newUser = User::factory()->create();

        $this->actingAs($admin)->get(route('organizations.members.create', $organization))->assertOk();
        $this->actingAs($admin)->post(route('organizations.members.store', $organization), ['user_id' => $newUser->id])
            ->assertRedirect(route('organizations.members.index', $organization));

        $this->assertDatabaseHas('organization_members', ['organization_id' => $organization->id, 'user_id' => $newUser->id]);
    }

    public function test_organization_owner_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newUser = User::factory()->create();

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $newUser->id])
            ->assertRedirect(route('organizations.members.index', $organization));

        $this->assertDatabaseHas('organization_members', ['organization_id' => $organization->id, 'user_id' => $newUser->id]);
    }

    public function test_a_user_cannot_add_a_member_to_another_organization(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $newUser = User::factory()->create();

        $this->actingAs($ownerA)->get(route('organizations.members.create', $orgB))->assertForbidden();
        $this->actingAs($ownerA)->post(route('organizations.members.store', $orgB), ['user_id' => $newUser->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_members', ['organization_id' => $orgB->id, 'user_id' => $newUser->id]);
    }

    public function test_direct_url_manipulation_of_organization_id_cannot_bypass_authorization(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $newUser = User::factory()->create();

        // ownerA is authorized for orgA only; submitting to orgB's URL must fail
        // even though the payload itself carries no organization_id field to trust.
        $this->actingAs($ownerA)->post(route('organizations.members.store', $orgB), ['user_id' => $newUser->id])
            ->assertForbidden();
    }

    // SEARCH

    public function test_authorized_administrator_can_search_existing_users(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $target = User::factory()->create(['name' => 'Findable Person']);

        $this->actingAs($owner)->get(route('organizations.members.create', [$organization, 'q' => 'Findable']))
            ->assertOk()
            ->assertSee('Findable Person');
    }

    public function test_search_can_find_by_username(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $target = User::factory()->create();
        UserProfile::create(['user_id' => $target->id, 'username' => 'uniqueusername']);

        $this->actingAs($owner)->get(route('organizations.members.create', [$organization, 'q' => 'uniqueusername']))
            ->assertOk()
            ->assertSee('uniqueusername');
    }

    public function test_search_can_find_by_exact_email(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $target = User::factory()->create(['email' => 'findme@example.com', 'name' => 'Email Target']);

        $this->actingAs($owner)->get(route('organizations.members.create', [$organization, 'q' => 'findme@example.com']))
            ->assertOk()
            ->assertSee('Email Target');
    }

    public function test_search_results_do_not_expose_password_or_security_data(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        User::factory()->create(['name' => 'Findable Person']);

        $response = $this->actingAs($owner)->get(route('organizations.members.create', [$organization, 'q' => 'Findable']));

        $response->assertOk();
        $response->assertDontSee('password', false);
    }

    public function test_search_is_organization_management_protected_for_guests(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->get(route('organizations.members.create', [$organization, 'q' => 'anything']))
            ->assertRedirect(route('login'));
    }

    public function test_search_does_not_return_results_for_queries_under_two_characters(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        User::factory()->create(['name' => 'A']);

        $this->actingAs($owner)->get(route('organizations.members.create', [$organization, 'q' => 'a']))
            ->assertOk()
            ->assertSee('Enter at least 2 characters');
    }

    // MEMBERSHIP

    public function test_new_member_receives_the_member_role(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newUser = User::factory()->create();

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $newUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue(User::find($newUser->id)->hasRole('Member'));
    }

    public function test_new_member_does_not_receive_admin_or_owner_role(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newUser = User::factory()->create();

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $newUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $refreshed = User::find($newUser->id);
        $this->assertFalse($refreshed->hasRole('Organization Admin'));
        $this->assertFalse($refreshed->hasRole('Organization Owner'));
    }

    public function test_client_supplied_role_field_is_ignored_on_add(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newUser = User::factory()->create();

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), [
            'user_id' => $newUser->id,
            'role' => 'Organization Owner',
            'organization_id' => 999999,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue(User::find($newUser->id)->hasRole('Member'));
        $this->assertFalse(User::find($newUser->id)->hasRole('Organization Owner'));
        $this->assertDatabaseHas('organization_members', ['organization_id' => $organization->id, 'user_id' => $newUser->id]);
    }

    public function test_duplicate_membership_is_prevented_with_a_clear_message(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $existingMember = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $existingMember->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $existingMember->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(1, OrganizationMember::where('organization_id', $organization->id)->where('user_id', $existingMember->id)->count());
    }

    public function test_database_uniqueness_constraint_prevents_duplicate_membership_rows(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $user = User::factory()->create();

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
    }

    public function test_membership_is_created_for_the_correct_organization(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $newUser = User::factory()->create();
        $this->actingAs($ownerA)->post(route('organizations.members.store', $orgA), ['user_id' => $newUser->id]);

        $this->assertDatabaseHas('organization_members', ['organization_id' => $orgA->id, 'user_id' => $newUser->id]);
        $this->assertDatabaseMissing('organization_members', ['organization_id' => $orgB->id, 'user_id' => $newUser->id]);
    }

    // ROLE ISOLATION

    public function test_member_role_is_scoped_to_the_organization_they_were_added_to(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $newUser = User::factory()->create();
        $this->actingAs($ownerA)->post(route('organizations.members.store', $orgA), ['user_id' => $newUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $this->assertTrue(User::find($newUser->id)->hasRole('Member'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $this->assertFalse(User::find($newUser->id)->hasRole('Member'));
    }

    public function test_adding_a_user_to_organization_a_does_not_affect_organization_b_permissions(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $sharedUser = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $sharedUser->id, 'status' => 'active']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $sharedUser->assignRole('Organization Admin');

        $this->actingAs($ownerA)->post(route('organizations.members.store', $orgA), ['user_id' => $sharedUser->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
        $this->assertTrue(User::find($sharedUser->id)->hasRole('Organization Admin'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $this->assertTrue(User::find($sharedUser->id)->hasRole('Member'));
        $this->assertFalse(User::find($sharedUser->id)->hasRole('Organization Admin'));
    }

    // FAILURE / VALIDATION

    public function test_an_invalid_user_id_cannot_be_added(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => 999999])
            ->assertSessionHasErrors('user_id');
    }

    public function test_missing_user_id_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), [])
            ->assertSessionHasErrors('user_id');
    }

    public function test_the_owner_cannot_be_added_as_a_member_of_their_own_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.members.store', $organization), ['user_id' => $owner->id])
            ->assertSessionHasErrors('user_id');
    }
}
