<?php

namespace Tests\Feature;

use App\Models\AudioCollection;
use App\Models\AudioResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudioCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => 'public',
        ]);
    }

    private function makeAudio(array $overrides = []): AudioResource
    {
        return AudioResource::create(array_merge([
            'title' => 'Track', 'slug' => 'track-'.uniqid(), 'status' => 'draft', 'visibility' => 'private',
        ], $overrides));
    }

    public function test_user_can_create_a_collection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('audio.collections.store'), [
            'title' => 'My Album', 'type' => 'album', 'visibility' => 'private',
        ]);

        $collection = AudioCollection::where('title', 'My Album')->first();
        $response->assertRedirect(route('audio.collections.show', $collection));
        $this->assertSame($user->id, $collection->user_id);
    }

    public function test_owner_can_add_audio_to_their_own_collection(): void
    {
        $user = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $user->id, 'title' => 'Album', 'slug' => 'album-1', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $audio = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.collections.items.store', $collection), ['audio_resource_id' => $audio->id])
            ->assertRedirect();

        $this->assertDatabaseHas('audio_collection_items', ['collection_id' => $collection->id, 'audio_resource_id' => $audio->id, 'position' => 1]);
    }

    public function test_adding_items_assigns_incrementing_positions(): void
    {
        $user = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $user->id, 'title' => 'Album', 'slug' => 'album-2', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $first = $this->makeAudio(['user_id' => $user->id]);
        $second = $this->makeAudio(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('audio.collections.items.store', $collection), ['audio_resource_id' => $first->id]);
        $this->actingAs($user)->post(route('audio.collections.items.store', $collection), ['audio_resource_id' => $second->id]);

        $this->assertDatabaseHas('audio_collection_items', ['audio_resource_id' => $first->id, 'position' => 1]);
        $this->assertDatabaseHas('audio_collection_items', ['audio_resource_id' => $second->id, 'position' => 2]);
    }

    public function test_owner_can_remove_audio_from_collection(): void
    {
        $user = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $user->id, 'title' => 'Album', 'slug' => 'album-3', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $audio = $this->makeAudio(['user_id' => $user->id]);
        $collection->addItem($audio);

        $this->actingAs($user)->delete(route('audio.collections.items.destroy', [$collection, $audio]))->assertRedirect();

        $this->assertDatabaseMissing('audio_collection_items', ['collection_id' => $collection->id, 'audio_resource_id' => $audio->id]);
    }

    public function test_reordering_updates_positions(): void
    {
        $user = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $user->id, 'title' => 'Album', 'slug' => 'album-4', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $a = $this->makeAudio(['user_id' => $user->id]);
        $b = $this->makeAudio(['user_id' => $user->id]);
        $collection->addItem($a);
        $collection->addItem($b);

        $this->actingAs($user)->put(route('audio.collections.reorder', $collection), ['order' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('audio_collection_items', ['audio_resource_id' => $b->id, 'position' => 1]);
        $this->assertDatabaseHas('audio_collection_items', ['audio_resource_id' => $a->id, 'position' => 2]);
    }

    public function test_reorder_rejects_a_list_that_does_not_match_current_items(): void
    {
        $user = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $user->id, 'title' => 'Album', 'slug' => 'album-5', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $a = $this->makeAudio(['user_id' => $user->id]);
        $collection->addItem($a);
        $foreignAudio = $this->makeAudio(['user_id' => User::factory()->create()->id]);

        $this->actingAs($user)->put(route('audio.collections.reorder', $collection), ['order' => [$foreignAudio->id]])
            ->assertSessionHasErrors('order');
    }

    public function test_unauthorized_user_cannot_modify_another_users_collection(): void
    {
        $owner = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $owner->id, 'title' => 'Album', 'slug' => 'album-6', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);
        $audio = $this->makeAudio(['user_id' => $owner->id]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('audio.collections.items.store', $collection), ['audio_resource_id' => $audio->id])
            ->assertForbidden();
    }

    public function test_a_manager_cannot_pull_another_owners_private_audio_into_their_collection(): void
    {
        $collectionOwner = User::factory()->create();
        $collection = AudioCollection::create(['user_id' => $collectionOwner->id, 'title' => 'Album', 'slug' => 'album-7', 'type' => 'album', 'status' => 'draft', 'visibility' => 'private']);

        $audioOwner = User::factory()->create();
        $foreignAudio = $this->makeAudio(['user_id' => $audioOwner->id, 'visibility' => 'private']);

        $this->actingAs($collectionOwner)->post(route('audio.collections.items.store', $collection), ['audio_resource_id' => $foreignAudio->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('audio_collection_items', ['collection_id' => $collection->id, 'audio_resource_id' => $foreignAudio->id]);
    }

    public function test_organization_collection_requires_manager_role(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->post(route('organizations.audio-collections.store', $organization), [
            'title' => 'Org Album', 'type' => 'album', 'visibility' => 'public',
        ])->assertRedirect();

        $this->assertDatabaseHas('audio_collections', ['title' => 'Org Album', 'organization_id' => $organization->id]);
    }
}
