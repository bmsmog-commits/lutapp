<?php

namespace Tests\Feature;

use App\Models\EventRsvp;
use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventRsvpAndCapacityTest extends TestCase
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

    private function makeEvent(Organization $organization, array $overrides = []): OrganizationEvent
    {
        return OrganizationEvent::create(array_merge([
            'organization_id' => $organization->id,
            'creator_id' => $organization->owner_id,
            'title' => 'Sunday Service',
            'slug' => 'sunday-service-'.uniqid(),
            'status' => 'published',
            'visibility' => 'public',
            'starts_at' => now()->addDays(3),
            'location_mode' => 'physical',
        ], $overrides));
    }

    // RSVP

    public function test_user_can_rsvp_to_a_published_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('org-events.rsvp.store', $event))->assertRedirect();

        $this->assertDatabaseHas('event_rsvps', ['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'attending']);
    }

    public function test_user_can_cancel_their_rsvp(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));

        $this->actingAs($user)->delete(route('org-events.rsvp.destroy', $event))->assertRedirect();

        $this->assertDatabaseHas('event_rsvps', ['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'cancelled']);
    }

    public function test_duplicate_rsvp_does_not_create_a_second_row(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));
        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));

        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->where('user_id', $user->id)->count());
    }

    public function test_re_rsvp_after_cancelling_flips_the_same_row_back_to_attending(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));
        $this->actingAs($user)->delete(route('org-events.rsvp.destroy', $event));

        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));

        $this->assertSame(1, EventRsvp::where('event_id', $event->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('event_rsvps', ['event_id' => $event->id, 'user_id' => $user->id, 'status' => 'attending']);
    }

    public function test_a_user_cannot_cancel_another_users_rsvp(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $victim = User::factory()->create();
        $this->actingAs($victim)->post(route('org-events.rsvp.store', $event));

        $attacker = User::factory()->create();
        $this->actingAs($attacker)->delete(route('org-events.rsvp.destroy', $event))->assertNotFound();

        $this->assertDatabaseHas('event_rsvps', ['event_id' => $event->id, 'user_id' => $victim->id, 'status' => 'attending']);
    }

    public function test_rsvp_to_a_draft_event_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['status' => 'draft']);

        // The owner can at least view their own draft (manager access), which
        // is what lets this reach the acceptsRsvps() check rather than being
        // stopped earlier by the view-authorization gate.
        $this->actingAs($owner)->post(route('org-events.rsvp.store', $event))->assertSessionHasErrors('rsvp');
        $this->assertSame(0, EventRsvp::count());
    }

    // CAPACITY

    public function test_event_with_no_capacity_accepts_unlimited_rsvps(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['capacity' => null]);

        foreach (range(1, 5) as $i) {
            $user = User::factory()->create();
            $this->actingAs($user)->post(route('org-events.rsvp.store', $event))->assertRedirect(route('org-events.show', $event));
        }

        $this->assertSame(5, $event->attendingCount());
    }

    public function test_rsvp_within_capacity_succeeds(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['capacity' => 3]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('org-events.rsvp.store', $event))->assertRedirect(route('org-events.show', $event));

        $this->assertFalse($event->isFull());
    }

    public function test_rsvp_at_capacity_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['capacity' => 1]);
        $first = User::factory()->create();
        $this->actingAs($first)->post(route('org-events.rsvp.store', $event));

        $second = User::factory()->create();
        $this->actingAs($second)->post(route('org-events.rsvp.store', $event))->assertSessionHasErrors('rsvp');

        $this->assertSame(1, $event->attendingCount());
    }

    public function test_a_cancelled_rsvp_frees_up_capacity(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['capacity' => 1]);
        $first = User::factory()->create();
        $this->actingAs($first)->post(route('org-events.rsvp.store', $event));
        $this->actingAs($first)->delete(route('org-events.rsvp.destroy', $event));

        $second = User::factory()->create();
        $this->actingAs($second)->post(route('org-events.rsvp.store', $event))->assertRedirect(route('org-events.show', $event));

        $this->assertSame(1, $event->attendingCount());
    }

    public function test_concurrent_style_rsvp_for_the_final_slot_only_admits_one(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['capacity' => 1]);
        $a = User::factory()->create();
        $b = User::factory()->create();

        // Sequential calls exercise the same locking path a real race would —
        // the row lock inside OrganizationEvent::rsvp() is what actually
        // prevents both succeeding, not test ordering.
        $event->rsvp($a);

        $this->expectException(\App\Models\EventFullException::class);
        $event->rsvp($b);
    }

    // LOCATION

    public function test_physical_event_stores_location_fields(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['location_mode' => 'physical', 'city' => 'Lagos', 'country' => 'Nigeria']);

        $this->assertSame('Lagos', $event->city);
    }

    public function test_online_event_online_url_hidden_from_non_rsvp_guest(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['location_mode' => 'online', 'online_url' => 'https://secret.example/meet']);

        $response = $this->get(route('org-events.show', $event));

        $response->assertDontSee('https://secret.example/meet');
    }

    public function test_online_event_url_visible_to_rsvpd_attendee(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['location_mode' => 'online', 'online_url' => 'https://secret.example/meet']);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('org-events.rsvp.store', $event));

        $response = $this->actingAs($user)->get(route('org-events.show', $event));

        $response->assertSee('https://secret.example/meet');
    }

    public function test_online_event_url_visible_to_manager_without_rsvp(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['location_mode' => 'online', 'online_url' => 'https://secret.example/meet']);

        $response = $this->actingAs($owner)->get(route('org-events.show', $event));

        $response->assertSee('https://secret.example/meet');
    }

    public function test_private_event_location_is_not_exposed_to_unauthorized_users(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization, ['visibility' => 'private', 'city' => 'Secret City']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('org-events.show', $event))->assertForbidden();
    }

    // MEDIA

    public function test_cover_upload_uses_media_storage_service(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);

        $this->actingAs($owner)->post(route('org-events.cover.store', $event), [
            'cover' => UploadedFile::fake()->image('cover.jpg')->size(50),
        ])->assertRedirect(route('org-events.show', $event));

        $event->refresh();
        $this->assertDatabaseHas('media_files', ['id' => $event->cover_media_id, 'organization_id' => $organization->id]);
    }

    public function test_replacing_the_cover_removes_the_old_media(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $this->actingAs($owner)->post(route('org-events.cover.store', $event), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $firstCover = $event->refresh()->cover;

        $this->actingAs($owner)->post(route('org-events.cover.store', $event), ['cover' => UploadedFile::fake()->image('b.jpg')->size(30)]);

        $this->assertDatabaseMissing('media_files', ['id' => $firstCover->id]);
    }

    public function test_removing_the_cover_deletes_the_media(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $this->actingAs($owner)->post(route('org-events.cover.store', $event), ['cover' => UploadedFile::fake()->image('a.jpg')->size(30)]);
        $cover = $event->refresh()->cover;

        $this->actingAs($owner)->delete(route('org-events.cover.destroy', $event));

        $this->assertDatabaseMissing('media_files', ['id' => $cover->id]);
        $this->assertNull($event->refresh()->cover_media_id);
    }

    public function test_non_manager_cannot_upload_a_cover(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $event = $this->makeEvent($organization);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('org-events.cover.store', $event), [
            'cover' => UploadedFile::fake()->image('a.jpg')->size(30),
        ])->assertForbidden();
    }

    // PERSONAL EVENTS REGRESSION

    public function test_personal_event_creation_still_works(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), [
            'title' => 'Personal Reminder', 'event_type' => 'meeting', 'starts_at' => now()->addDay()->toDateTimeString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('events', ['title' => 'Personal Reminder', 'user_id' => $user->id]);
    }

    public function test_personal_event_listing_still_works(): void
    {
        $user = User::factory()->create();
        $user->events()->create([
            'title' => 'My Reminder', 'event_type' => 'meeting', 'starts_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->get(route('events.index'))->assertOk()->assertSee('My Reminder');
    }

    public function test_personal_event_deletion_still_works(): void
    {
        $user = User::factory()->create();
        $event = $user->events()->create(['title' => 'Delete Me', 'event_type' => 'meeting', 'starts_at' => now()->addDay()]);

        $this->actingAs($user)->delete(route('events.destroy', $event))->assertRedirect();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_personal_event_cannot_be_deleted_by_another_user(): void
    {
        $owner = User::factory()->create();
        $event = $owner->events()->create(['title' => 'Protected', 'event_type' => 'meeting', 'starts_at' => now()->addDay()]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete(route('events.destroy', $event))->assertNotFound();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
