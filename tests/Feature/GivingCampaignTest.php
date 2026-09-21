<?php

namespace Tests\Feature;

use App\Models\GivingCampaign;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GivingCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    private function makeCampaign(Organization $organization, array $overrides = []): GivingCampaign
    {
        return GivingCampaign::create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Building Fund',
            'slug' => 'building-fund-'.uniqid(),
            'currency' => 'NGN',
            'status' => 'draft',
            'visibility' => 'private',
        ], $overrides));
    }

    // CREATION / AUTHORIZATION

    public function test_organization_owner_can_create_a_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $response = $this->actingAs($owner)->post(route('giving.store', $organization), [
            'title' => 'New Roof Fund', 'currency' => 'NGN', 'visibility' => 'public',
        ]);

        $campaign = GivingCampaign::where('title', 'New Roof Fund')->first();
        $response->assertRedirect(route('giving.show', [$organization, $campaign]));
        $this->assertSame('draft', $campaign->status);
        $this->assertSame($organization->id, $campaign->organization_id);
    }

    public function test_plain_member_cannot_create_a_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('giving.store', $organization), [
            'title' => 'X', 'currency' => 'NGN', 'visibility' => 'public',
        ])->assertForbidden();
    }

    public function test_a_restricted_owner_cannot_create_a_campaign(): void
    {
        $owner = User::factory()->create(['account_status' => 'restricted']);
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('giving.store', $organization), [
            'title' => 'Restricted Fund', 'currency' => 'NGN', 'visibility' => 'public',
        ])->assertForbidden();

        $this->assertDatabaseMissing('giving_campaigns', ['title' => 'Restricted Fund']);
    }

    public function test_organization_admin_can_create_a_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $this->actingAs($admin)->post(route('giving.store', $organization), [
            'title' => 'Admin Campaign', 'currency' => 'NGN', 'visibility' => 'public',
        ])->assertRedirect();

        $this->assertDatabaseHas('giving_campaigns', ['title' => 'Admin Campaign']);
    }

    public function test_currency_must_be_a_supported_currency(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('giving.store', $organization), [
            'title' => 'X', 'currency' => 'XYZ', 'visibility' => 'public',
        ])->assertSessionHasErrors('currency');
    }

    public function test_target_amount_is_stored_as_integer_minor_units(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('giving.store', $organization), [
            'title' => 'Precise Fund', 'currency' => 'NGN', 'visibility' => 'public', 'target_amount' => '12345.67',
        ]);

        $campaign = GivingCampaign::where('title', 'Precise Fund')->first();
        $this->assertSame(1234567, $campaign->target_amount);
        $this->assertSame('12345.67', $campaign->targetAmountDisplay());
    }

    // LIFECYCLE

    public function test_owner_can_publish_close_and_cancel_a_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);

        $this->actingAs($owner)->post(route('giving.publish', [$organization, $campaign]));
        $this->assertSame('published', $campaign->refresh()->status);

        $this->actingAs($owner)->post(route('giving.close', [$organization, $campaign]));
        $this->assertSame('closed', $campaign->refresh()->status);
    }

    public function test_owner_can_cancel_a_draft_campaign(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);

        $this->actingAs($owner)->post(route('giving.cancel', [$organization, $campaign]));

        $this->assertSame('cancelled', $campaign->refresh()->status);
    }

    public function test_non_manager_cannot_publish_close_or_cancel(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'published']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('giving.publish', [$organization, $campaign]))->assertForbidden();
        $this->actingAs($intruder)->post(route('giving.close', [$organization, $campaign]))->assertForbidden();
        $this->actingAs($intruder)->post(route('giving.cancel', [$organization, $campaign]))->assertForbidden();
    }

    // VISIBILITY

    public function test_published_public_campaign_is_visible_to_guests(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Open Fund']);

        $this->get(route('giving.show', [$organization, $campaign]))->assertOk()->assertSee('Open Fund');
    }

    public function test_draft_campaign_is_not_visible_to_guests(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'draft', 'visibility' => 'public']);

        $this->get(route('giving.show', [$organization, $campaign]))->assertForbidden();
    }

    public function test_private_campaign_is_not_visible_to_outsiders(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'private']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('giving.show', [$organization, $campaign]))->assertForbidden();
        $this->get(route('giving.show', [$organization, $campaign]))->assertForbidden();
    }

    public function test_private_campaign_is_visible_to_organization_members(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'private']);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->get(route('giving.show', [$organization, $campaign]))->assertOk();
    }

    public function test_campaign_of_a_private_organization_never_leaks_even_if_marked_public(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $campaign = $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'public']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('giving.show', [$organization, $campaign]))->assertForbidden();
        $this->get(route('giving.show', [$organization, $campaign]))->assertForbidden();
    }

    public function test_private_campaign_does_not_appear_in_the_organizations_public_campaign_listing(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'private', 'title' => 'Hidden Fund']);
        $this->makeCampaign($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Visible Fund']);

        $this->get(route('giving.index', $organization))->assertSee('Visible Fund')->assertDontSee('Hidden Fund');
    }

    // ORGANIZATION ISOLATION

    public function test_organization_a_member_cannot_manage_organization_bs_campaign(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $campaignB = $this->makeCampaign($orgB, ['status' => 'published', 'visibility' => 'public']);

        $this->actingAs($ownerA)->put(route('giving.update', [$orgB, $campaignB]), [
            'title' => 'Hijacked', 'currency' => 'NGN', 'visibility' => 'public',
        ])->assertForbidden();
    }

    public function test_a_campaign_id_cannot_be_accessed_through_an_unrelated_organization_route(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $campaignB = $this->makeCampaign($orgB, ['status' => 'published', 'visibility' => 'public']);

        $this->get(route('giving.show', [$orgA, $campaignB]))->assertNotFound();
    }
}
