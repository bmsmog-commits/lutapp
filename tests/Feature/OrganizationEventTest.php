<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationEventTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    private function makeEvent(Organization $organization, array $overrides = []): OrganizationEvent
    {
        return OrganizationEvent::create(array_merge([
            'organization_id' => $organization->id,
            'creator_id' => $organization->owner_id,
            'title' => 'Sunday Service',
            'slug' => 'sunday-service-'.uniqid(),
            'status' => 'draft',
            'visibility' => 'private',
            'starts_at' => now()->addDays(3),
            'location_mode' => 'physical',
        ], $overrides));
    }

    // CREATION / AUTHORIZATION

    public function test_organization_owner_can_create_an_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $response = $this->actingAs($owner)->post(route('org-events.store', $organization), [
            'title' => 'Youth Conference', 'visibility' => 'public', 'location_mode' => 'physical',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        ]);

        $event = OrganizationEvent::where('title', 'Youth Conference')->first();
        $response->assertRedirect(route('org-events.show', $event));
        $this->assertSame('draft', $event->status);
        $this->assertSame($organization->id, $event->organization_id);
        $this->assertSame($owner->id, $event->creator_id);
    }

    public function test_plain_member_cannot_create_an_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('org-events.store', $organization), [
            'title' => 'X', 'visibility' => 'public', 'location_mode' => 'physical', 'starts_at' => now()->addWeek(),
        ])->assertForbidden();
    }

    public function test_a_restricted_owner_cannot_create_an_event(): void
    {
        $owner = User::factory()->create(['account_status' => 'restricted']);
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('org-events.store', $organization), [
            'title' => 'X', 'visibility' => 'public', 'location_mode' => 'physical', 'starts_at' => now()->addWeek(),
        ])->assertForbidden();

        $this->assertDatabaseMissing('organization_events', ['title' => 'X']);
    }

    public function test_organization_admin_can_create_an_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'status' => 'active']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole('Organization Admin');

        $this->actingAs($admin)->post(route('org-events.store', $organization), [
            'title' => 'Admin Event', 'visibility' => 'public', 'location_mode' => 'physical', 'starts_at' => now()->addWeek(),
        ])->assertRedirect();

        $this->assertDatabaseHas('organization_events', ['title' => 'Admin Event']);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('org-events.store', $organization), [
            'title' => 'X', 'visibility' => 'public', 'location_mode' => 'physical',
            'starts_at' => now()->addWeek(), 'ends_at' => now()->subDay(),
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_online_event_requires_an_online_url(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('org-events.store', $organization), [
            'title' => 'Online Study', 'visibility' => 'public', 'location_mode' => 'online', 'starts_at' => now()->addWeek(),
        ])->assertSessionHasErrors('online_url');
    }

    public function test_online_event_clears_physical_location_fields(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('org-events.store', $organization), [
            'title' => 'Online Study', 'visibility' => 'public', 'location_mode' => 'online',
            'starts_at' => now()->addWeek(), 'online_url' => 'https://example.com/meet',
            'city' => 'Lagos', 'country' => 'Nigeria',
        ]);

        $event = OrganizationEvent::where('title', 'Online Study')->first();
        $this->assertNull($event->city);
        $this->assertNull($event->country);
    }

    // LIFECYCLE

    public function test_owner_can_publish_complete_and_cancel(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);

        $this->actingAs($owner)->post(route('org-events.publish', $event));
        $this->assertSame('published', $event->refresh()->status);

        $this->actingAs($owner)->post(route('org-events.complete', $event));
        $this->assertSame('completed', $event->refresh()->status);
    }

    public function test_owner_can_cancel_a_published_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'published']);

        $this->actingAs($owner)->post(route('org-events.cancel', $event));

        $this->assertSame('cancelled', $event->refresh()->status);
    }

    public function test_non_manager_cannot_change_event_status(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'published']);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('org-events.publish', $event))->assertForbidden();
        $this->actingAs($intruder)->post(route('org-events.cancel', $event))->assertForbidden();
        $this->actingAs($intruder)->post(route('org-events.complete', $event))->assertForbidden();
    }

    // VISIBILITY

    public function test_published_public_event_is_visible_to_guests(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Open Crusade']);

        $this->get(route('org-events.show', $event))->assertOk()->assertSee('Open Crusade');
    }

    public function test_draft_event_is_not_publicly_discoverable(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'draft', 'visibility' => 'public', 'title' => 'Draft Event']);

        $this->get(route('org-events.discover'))->assertDontSee('Draft Event');
        $this->get(route('org-events.show', $event))->assertForbidden();
    }

    public function test_cancelled_event_is_not_publicly_discoverable(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'cancelled', 'visibility' => 'public', 'title' => 'Cancelled Event']);

        $this->get(route('org-events.discover'))->assertDontSee('Cancelled Event');
    }

    public function test_private_event_does_not_appear_publicly_but_is_visible_to_members(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'private']);
        $outsider = User::factory()->create();
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->get(route('org-events.show', $event))->assertForbidden();
        $this->actingAs($outsider)->get(route('org-events.show', $event))->assertForbidden();
        $this->actingAs($member)->get(route('org-events.show', $event))->assertOk();
    }

    public function test_event_of_a_private_organization_never_leaks_even_if_marked_public(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');
        $event = $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('org-events.show', $event))->assertForbidden();
        $this->get(route('org-events.discover'))->assertDontSee($event->title);
    }

    public function test_direct_url_to_a_private_event_id_is_protected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'private']);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->get(route('org-events.show', $event->id))->assertForbidden();
    }

    // SEARCH / FILTERS

    public function test_search_matches_title_and_organization_name(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Findable Retreat']);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Other Event']);

        $this->get(route('org-events.discover', ['q' => 'Findable']))->assertSee('Findable Retreat')->assertDontSee('Other Event');
    }

    public function test_category_filter_works(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Bible Class', 'category' => 'Bible Study']);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Big Conf', 'category' => 'Conference']);

        $this->get(route('org-events.discover', ['category' => 'Bible Study']))->assertSee('Bible Class')->assertDontSee('Big Conf');
    }

    public function test_date_filter_works(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Today Event', 'starts_at' => now()->setTime(10, 0)]);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Future Event', 'starts_at' => now()->addMonth()]);

        $this->get(route('org-events.discover', ['date' => now()->format('Y-m-d')]))
            ->assertSee('Today Event')->assertDontSee('Future Event');
    }

    public function test_location_mode_filter_works(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Physical Meet', 'location_mode' => 'physical']);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Online Meet', 'location_mode' => 'online', 'online_url' => 'https://x.test']);

        $this->get(route('org-events.discover', ['location_mode' => 'online']))->assertSee('Online Meet')->assertDontSee('Physical Meet');
    }

    public function test_city_filter_works(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Lagos Event', 'city' => 'Lagos']);
        $this->makeEvent($organization, ['status' => 'published', 'visibility' => 'public', 'title' => 'Abuja Event', 'city' => 'Abuja']);

        $this->get(route('org-events.discover', ['city' => 'Lagos']))->assertSee('Lagos Event')->assertDontSee('Abuja Event');
    }

    // ORGANIZATION ISOLATION

    public function test_organization_a_cannot_manage_organization_bs_event(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $eventB = $this->makeEvent($orgB, ['status' => 'published', 'visibility' => 'public']);

        $this->actingAs($ownerA)->put(route('org-events.update', $eventB), [
            'title' => 'Hijacked', 'visibility' => 'public', 'location_mode' => 'physical', 'starts_at' => now()->addWeek(),
        ])->assertForbidden();
    }

    public function test_organization_a_cannot_view_organization_bs_private_attendance(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $eventB = $this->makeEvent($orgB, ['status' => 'published', 'visibility' => 'public']);

        $this->actingAs($ownerA)->get(route('org-events.attendance', $eventB))->assertForbidden();
    }
}
