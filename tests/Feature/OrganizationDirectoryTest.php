<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function makeOrganization(array $overrides = []): Organization
    {
        $owner = $overrides['owner_id'] ?? User::factory()->create()->id;

        return Organization::create(array_merge([
            'owner_id' => $owner,
            'name' => 'Test Org '.uniqid(),
            'slug' => 'test-org-'.uniqid(),
            'type' => 'church',
            'visibility' => 'public',
            'status' => 'active',
        ], $overrides));
    }

    // DIRECTORY LISTING

    public function test_public_organization_appears_in_the_directory(): void
    {
        $this->makeOrganization(['name' => 'Grace Chapel', 'visibility' => 'public']);

        $this->get(route('directory.index'))->assertOk()->assertSee('Grace Chapel');
    }

    public function test_private_organization_does_not_appear_in_the_directory(): void
    {
        $owner = User::factory()->create();
        $this->makeOrganization(['owner_id' => $owner->id, 'name' => 'Secret Org', 'visibility' => 'private']);

        $this->get(route('directory.index'))->assertDontSee('Secret Org');

        // Not even to the owner themself, browsing the public listing.
        $this->actingAs($owner)->get(route('directory.index'))->assertDontSee('Secret Org');
    }

    public function test_directory_listing_is_paginated(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makeOrganization(['name' => "Paginated Org {$i}"]);
        }

        $response = $this->get(route('directory.index'));

        $this->assertSame(12, $response->viewData('organizations')->count());
        $this->assertTrue($response->viewData('organizations')->hasMorePages());
    }

    public function test_directory_search_matches_name(): void
    {
        $this->makeOrganization(['name' => 'Findable Ministries']);
        $this->makeOrganization(['name' => 'Other Org']);

        $this->get(route('directory.index', ['q' => 'Findable']))
            ->assertSee('Findable Ministries')->assertDontSee('Other Org');
    }

    public function test_directory_search_matches_city(): void
    {
        $this->makeOrganization(['name' => 'Lagos Church', 'city' => 'Lagos']);
        $this->makeOrganization(['name' => 'Abuja Church', 'city' => 'Abuja']);

        $this->get(route('directory.index', ['q' => 'Lagos']))
            ->assertSee('Lagos Church')->assertDontSee('Abuja Church');
    }

    public function test_directory_type_filter(): void
    {
        $this->makeOrganization(['name' => 'A Church', 'type' => 'church']);
        $this->makeOrganization(['name' => 'A Company', 'type' => 'company']);

        $this->get(route('directory.index', ['type' => 'company']))
            ->assertSee('A Company')->assertDontSee('A Church');
    }

    public function test_directory_country_filter(): void
    {
        $this->makeOrganization(['name' => 'Nigeria Org', 'country' => 'Nigeria']);
        $this->makeOrganization(['name' => 'Ghana Org', 'country' => 'Ghana']);

        $this->get(route('directory.index', ['country' => 'Ghana']))
            ->assertSee('Ghana Org')->assertDontSee('Nigeria Org');
    }

    public function test_directory_state_filter(): void
    {
        $this->makeOrganization(['name' => 'Lagos State Org', 'state' => 'Lagos']);
        $this->makeOrganization(['name' => 'Kano State Org', 'state' => 'Kano']);

        $this->get(route('directory.index', ['state' => 'Kano']))
            ->assertSee('Kano State Org')->assertDontSee('Lagos State Org');
    }

    public function test_directory_city_filter(): void
    {
        $this->makeOrganization(['name' => 'Ikeja Org', 'city' => 'Ikeja']);
        $this->makeOrganization(['name' => 'Yaba Org', 'city' => 'Yaba']);

        $this->get(route('directory.index', ['city' => 'Yaba']))
            ->assertSee('Yaba Org')->assertDontSee('Ikeja Org');
    }

    public function test_directory_has_location_filter(): void
    {
        $this->makeOrganization(['name' => 'Mapped Org', 'latitude' => 6.5, 'longitude' => 3.4]);
        $this->makeOrganization(['name' => 'Remote Org', 'latitude' => null, 'longitude' => null]);

        $this->get(route('directory.index', ['has_location' => '1']))
            ->assertSee('Mapped Org')->assertDontSee('Remote Org');

        $this->get(route('directory.index', ['has_location' => '0']))
            ->assertSee('Remote Org')->assertDontSee('Mapped Org');
    }

    // PUBLIC PROFILE

    public function test_public_organization_profile_is_viewable(): void
    {
        $organization = $this->makeOrganization(['name' => 'Open Church', 'description' => 'Everyone welcome']);

        $this->get(route('directory.show', $organization))->assertOk()->assertSee('Open Church')->assertSee('Everyone welcome');
    }

    public function test_private_organization_profile_is_denied_to_guests_and_outsiders(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id, 'visibility' => 'private']);

        $this->get(route('directory.show', $organization))->assertForbidden();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('directory.show', $organization))->assertForbidden();
    }

    public function test_private_organization_profile_remains_viewable_to_its_own_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id, 'visibility' => 'private']);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('directory.show', $organization))->assertOk();
    }

    public function test_direct_id_access_to_a_private_organization_is_denied(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id, 'visibility' => 'private']);

        $attacker = User::factory()->create();
        $this->actingAs($attacker)->get(route('directory.show', $organization->id))->assertForbidden();
    }

    // AUTHORIZATION OVER DIRECTORY DATA

    public function test_owner_can_manage_organization_directory_data(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => 'Renamed Org', 'slug' => $organization->slug, 'type' => 'church', 'visibility' => 'public',
        ])->assertRedirect();

        $this->assertSame('Renamed Org', $organization->refresh()->name);
    }

    public function test_unauthorized_member_cannot_edit_directory_data(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->put(route('organizations.update', $organization), [
            'name' => 'Hijacked', 'slug' => $organization->slug, 'type' => 'church', 'visibility' => 'public',
        ])->assertForbidden();
    }

    public function test_cross_organization_isolation_on_management(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->makeOrganization(['owner_id' => $ownerA->id]);
        $ownerB = User::factory()->create();
        $orgB = $this->makeOrganization(['owner_id' => $ownerB->id]);

        $this->actingAs($ownerA)->put(route('organizations.update', $orgB), [
            'name' => 'Hijacked B', 'slug' => $orgB->slug, 'type' => 'church', 'visibility' => 'public',
        ])->assertForbidden();
    }

    // LOCATION

    public function test_valid_coordinates_are_accepted(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => 'church', 'visibility' => 'public',
            'latitude' => 6.5244, 'longitude' => 3.3792,
        ])->assertRedirect();

        $organization->refresh();
        $this->assertEqualsWithDelta(6.5244, (float) $organization->latitude, 0.001);
        $this->assertEqualsWithDelta(3.3792, (float) $organization->longitude, 0.001);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);

        $this->actingAs($owner)->put(route('organizations.update', $organization), [
            'name' => $organization->name, 'slug' => $organization->slug, 'type' => 'church', 'visibility' => 'public',
            'latitude' => 200, 'longitude' => 3.3792,
        ])->assertSessionHasErrors('latitude');
    }

    public function test_private_organization_coordinates_are_not_exposed_through_the_public_directory(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization([
            'owner_id' => $owner->id, 'name' => 'Hidden Coordinates Org',
            'visibility' => 'private', 'latitude' => 6.5, 'longitude' => 3.4,
        ]);

        $this->get(route('directory.index', ['lat' => 6.5, 'lng' => 3.4, 'radius_km' => 25]))
            ->assertDontSee('Hidden Coordinates Org');
    }

    public function test_nearby_search_finds_organizations_within_radius(): void
    {
        // Roughly 1km apart.
        $this->makeOrganization(['name' => 'Nearby Org', 'latitude' => 6.5244, 'longitude' => 3.3792]);
        // Roughly 400km+ away (different state entirely).
        $this->makeOrganization(['name' => 'Far Org', 'latitude' => 9.0765, 'longitude' => 7.3986]);

        $response = $this->get(route('directory.index', ['lat' => 6.5244, 'lng' => 3.3792, 'radius_km' => 10]));

        $response->assertSee('Nearby Org')->assertDontSee('Far Org');
    }

    // MEDIA / LOGO

    public function test_organization_logo_appears_on_the_directory_profile(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.jpg')->size(50),
        ]);
        $organization->refresh();

        $this->get(route('directory.show', $organization))->assertOk();
        $this->assertNotNull($organization->logo_media_id);
        $this->assertSame('public', $organization->logo->visibility);
    }

    public function test_private_organizations_logo_does_not_leak_through_the_files_route(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id, 'visibility' => 'private']);
        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.jpg')->size(50),
        ]);
        $organization->refresh();
        Auth::logout();

        $this->get(route('files.show', $organization->logo_media_id))->assertNotFound();
    }

    public function test_media_storage_service_remains_the_source_of_truth_for_the_logo(): void
    {
        $owner = User::factory()->create();
        $organization = $this->makeOrganization(['owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('organizations.logo.store', $organization), [
            'logo' => UploadedFile::fake()->image('logo.jpg')->size(50),
        ]);

        $organization->refresh();
        $this->assertDatabaseHas('media_files', ['id' => $organization->logo_media_id, 'organization_id' => $organization->id]);
    }
}
