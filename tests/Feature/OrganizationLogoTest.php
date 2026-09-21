<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationLogoTest extends TestCase
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

    public function test_organization_owner_can_upload_a_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png')->size(50),
        ])->assertRedirect(route('organizations.edit', $organization));

        $this->assertNotNull($organization->refresh()->logo);
    }

    public function test_organization_admin_can_upload_a_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $this->actingAs($admin)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png')->size(50),
        ])->assertRedirect(route('organizations.edit', $organization));

        $this->assertNotNull($organization->refresh()->logo);
    }

    public function test_a_plain_member_cannot_change_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.png')->size(50),
        ])->assertForbidden();

        $this->assertNull($organization->refresh()->logo_media_id);
    }

    public function test_owner_can_replace_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('first.png')->size(30)]);
        $firstLogo = $organization->refresh()->logo;

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('second.png')->size(30)]);
        $organization->refresh();

        $this->assertNotEquals($firstLogo->id, $organization->logo_media_id);
        $this->assertDatabaseMissing('media_files', ['id' => $firstLogo->id]);
    }

    public function test_authorized_user_can_remove_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('logo.png')->size(30)]);
        $logo = $organization->refresh()->logo;

        $this->actingAs($owner)->delete(route('organizations.logo.destroy', $organization))->assertRedirect(route('organizations.edit', $organization));

        $this->assertNull($organization->refresh()->logo_media_id);
        $this->assertDatabaseMissing('media_files', ['id' => $logo->id]);
    }

    public function test_unauthorized_user_cannot_remove_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('logo.png')->size(30)]);

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->delete(route('organizations.logo.destroy', $organization))->assertForbidden();

        $this->assertNotNull($organization->refresh()->logo_media_id);
    }

    public function test_user_from_another_organization_cannot_modify_the_logo(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $this->actingAs($ownerB)->post(route('organizations.logo.store', $orgA), [
            'logo' => UploadedFile::fake()->image('logo.png')->size(30),
        ])->assertForbidden();

        $this->assertNull($orgA->refresh()->logo_media_id);
    }

    public function test_cross_organization_url_manipulation_fails(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');

        $this->actingAs($ownerA)->post(route('organizations.logo.store', $orgA), ['logo' => UploadedFile::fake()->image('a.png')->size(30)]);
        $logoA = $orgA->refresh()->logo;

        // ownerB is authorized for orgB but has no authority over orgA's logo,
        // even by hitting orgA's own route directly.
        $this->actingAs($ownerB)->delete(route('organizations.logo.destroy', $orgA))->assertForbidden();
        $this->assertDatabaseHas('media_files', ['id' => $logoA->id]);
    }

    public function test_invalid_logo_file_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('logo');

        $this->assertNull($organization->refresh()->logo_media_id);
    }

    public function test_executable_disguised_as_logo_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->create('shell.php', 10, 'image/png'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_previous_logo_remains_if_replacement_is_invalid(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('good.png')->size(30)]);
        $originalLogo = $organization->refresh()->logo;

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->create('bad.php', 10, 'image/png'),
        ])->assertSessionHasErrors('logo');

        $this->assertSame($originalLogo->id, $organization->refresh()->logo_media_id);
    }

    // VISIBILITY

    public function test_a_public_organizations_logo_is_viewable_by_a_guest(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'grace-chapel', 'public');
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('logo.png')->size(30)]);
        $logo = $organization->refresh()->logo;

        $this->assertSame('public', $logo->visibility);
        $this->get(route('files.show', $logo))->assertOk();
    }

    public function test_a_private_organizations_logo_is_not_publicly_accessible(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('logo.png')->size(30)]);
        $logo = $organization->refresh()->logo;

        $this->assertSame('private', $logo->visibility);

        // actingAs() sets the auth guard directly for the rest of the test rather
        // than via session/cookies, so it must be explicitly cleared here to
        // actually simulate an unauthenticated guest request afterward.
        $this->app['auth']->forgetGuards();
        $this->get(route('files.show', $logo))->assertNotFound();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('files.show', $logo))->assertForbidden();
    }

    public function test_an_active_member_can_view_a_private_organizations_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), ['logo' => UploadedFile::fake()->image('logo.png')->size(30)]);
        $logo = $organization->refresh()->logo;

        $this->actingAs($member)->get(route('files.show', $logo))->assertOk();
    }
}
