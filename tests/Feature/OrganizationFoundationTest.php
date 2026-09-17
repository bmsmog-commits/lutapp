<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_can_be_created_with_a_unique_username(): void
    {
        $user = User::factory()->create();

        $profile = UserProfile::create([
            'user_id' => $user->id,
            'username' => 'gabriel',
        ]);

        $this->assertDatabaseHas('user_profiles', ['username' => 'gabriel']);
        $this->assertTrue($user->profile->is($profile));
    }

    public function test_username_must_be_unique(): void
    {
        UserProfile::create(['user_id' => User::factory()->create()->id, 'username' => 'gabriel']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        UserProfile::create(['user_id' => User::factory()->create()->id, 'username' => 'gabriel']);
    }

    public function test_languages_can_be_created_and_queried(): void
    {
        Language::create(['code' => 'en', 'name' => 'English', 'native_name' => 'English']);

        $this->assertDatabaseHas('languages', ['code' => 'en']);
    }

    public function test_user_language_preference_is_independent_of_bible_translation(): void
    {
        $user = User::factory()->create();
        $user->preferences()->create(['language' => 'yo']);

        $this->assertSame('yo', $user->preferences->language);
    }

    public function test_organization_can_be_created_with_a_unique_slug(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'owner_id' => $owner->id,
            'name' => 'Grace Chapel',
            'slug' => 'grace-chapel',
            'type' => 'church',
        ]);

        $this->assertDatabaseHas('organizations', ['slug' => 'grace-chapel']);
        $this->assertTrue($organization->owner->is($owner));
    }

    public function test_organization_slug_must_be_unique(): void
    {
        Organization::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Grace Chapel',
            'slug' => 'grace-chapel',
            'type' => 'church',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Organization::create([
            'owner_id' => User::factory()->create()->id,
            'name' => 'Another Grace Chapel',
            'slug' => 'grace-chapel',
            'type' => 'church',
        ]);
    }

    public function test_creating_an_organization_assigns_the_owner_role_to_its_owner(): void
    {
        $owner = User::factory()->create();

        $organization = Organization::create([
            'owner_id' => $owner->id,
            'name' => 'ABC Technologies',
            'slug' => 'abc-technologies',
            'type' => 'company',
        ]);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        $this->assertTrue($owner->refresh()->hasRole('Organization Owner'));
    }

    public function test_a_user_can_belong_to_multiple_organizations(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Org A', 'slug' => 'org-a', 'type' => 'church']);
        $orgB = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Org B', 'slug' => 'org-b', 'type' => 'company']);

        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $user->id, 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->assertCount(2, $user->organizations);
    }

    public function test_duplicate_active_membership_is_prevented(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['owner_id' => User::factory()->create()->id, 'name' => 'Org A', 'slug' => 'org-a', 'type' => 'church']);

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => 'active']);
    }

    public function test_a_private_organization_is_not_visible_to_non_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $organization = Organization::create([
            'owner_id' => $owner->id,
            'name' => 'Private Org',
            'slug' => 'private-org',
            'type' => 'ngo',
            'visibility' => 'private',
        ]);

        $this->assertFalse($outsider->can('view', $organization));
        $this->assertTrue($owner->can('view', $organization));
    }

    public function test_a_member_from_organization_a_cannot_manage_organization_b_members(): void
    {
        $orgAOwner = User::factory()->create();
        $orgBOwner = User::factory()->create();

        $orgA = Organization::create(['owner_id' => $orgAOwner->id, 'name' => 'Org A', 'slug' => 'org-a', 'type' => 'church']);
        $orgB = Organization::create(['owner_id' => $orgBOwner->id, 'name' => 'Org B', 'slug' => 'org-b', 'type' => 'company']);

        $memberB = OrganizationMember::create(['organization_id' => $orgB->id, 'user_id' => User::factory()->create()->id, 'status' => 'active']);

        // Org A's owner has no authority whatsoever over Org B's membership records.
        $this->assertFalse($orgAOwner->can('update', $memberB));
        $this->assertFalse($orgAOwner->can('delete', $memberB));
        $this->assertTrue($orgBOwner->can('update', $memberB));
    }

    public function test_a_plain_member_cannot_promote_themselves(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create(['owner_id' => $owner->id, 'name' => 'Org A', 'slug' => 'org-a', 'type' => 'church']);

        $member = User::factory()->create();
        $membership = OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $member->assignRole('Member');

        $this->assertFalse($member->can('update', $membership));
    }

    public function test_organization_uses_explicit_fillable_and_rejects_mass_assignment_of_owner_id_from_request_input(): void
    {
        $reflection = new \ReflectionClass(Organization::class);
        $fillableAttribute = $reflection->getAttributes(\Illuminate\Database\Eloquent\Attributes\Fillable::class);

        $this->assertNotEmpty($fillableAttribute, 'Organization must declare an explicit #[Fillable] attribute, never $guarded = [].');
    }
}
