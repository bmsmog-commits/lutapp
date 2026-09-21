<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceCreationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    public function test_user_can_create_a_personal_resource(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('resources.store'), [
            'title' => 'My Devotional', 'visibility' => 'private',
        ]);

        $resource = Resource::where('title', 'My Devotional')->first();
        $response->assertRedirect(route('resources.show', $resource));
        $this->assertSame($user->id, $resource->user_id);
        $this->assertNull($resource->organization_id);
        $this->assertSame('draft', $resource->status);
        $this->assertSame('book', $resource->type);
    }

    public function test_organization_admin_can_create_an_organization_resource(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $response = $this->actingAs($owner)->post(route('organizations.resources.store', $organization), [
            'title' => 'Bible Study Guide', 'visibility' => 'public',
        ]);

        $resource = Resource::where('title', 'Bible Study Guide')->first();
        $response->assertRedirect(route('resources.show', $resource));
        $this->assertSame($organization->id, $resource->organization_id);
        $this->assertNull($resource->user_id);
    }

    public function test_a_plain_member_cannot_create_an_organization_resource(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $this->actingAs($member)->post(route('organizations.resources.store', $organization), ['title' => 'X', 'visibility' => 'public'])
            ->assertForbidden();
    }

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('resources.store'), ['visibility' => 'private'])
            ->assertSessionHasErrors('title');
    }

    public function test_resource_can_have_a_valid_language_association(): void
    {
        $user = User::factory()->create();
        $language = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'native_name' => 'Yorùbá']);

        $this->actingAs($user)->post(route('resources.store'), [
            'title' => 'Yoruba Devotional', 'visibility' => 'private', 'language_id' => $language->id,
        ]);

        $resource = Resource::where('title', 'Yoruba Devotional')->first();
        $this->assertSame($language->id, $resource->language_id);
    }

    public function test_an_invalid_language_id_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('resources.store'), [
            'title' => 'X', 'visibility' => 'private', 'language_id' => 999999,
        ])->assertSessionHasErrors('language_id');
    }

    public function test_resource_category_must_be_one_of_the_known_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('resources.store'), [
            'title' => 'X', 'visibility' => 'private', 'category' => 'Not A Real Category',
        ])->assertSessionHasErrors('category');
    }

    public function test_a_resource_in_a_private_organization_is_forced_private_regardless_of_requested_visibility(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'private-org', 'private');

        $this->actingAs($owner)->post(route('organizations.resources.store', $organization), [
            'title' => 'Internal Doc', 'visibility' => 'public',
        ]);

        $resource = Resource::where('title', 'Internal Doc')->first();
        $this->assertSame('private', $resource->visibility);
    }

    public function test_slug_is_generated_and_unique(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('resources.store'), ['title' => 'Same Title', 'visibility' => 'public']);
        $this->actingAs($user)->post(route('resources.store'), ['title' => 'Same Title', 'visibility' => 'public']);

        $slugs = Resource::where('title', 'Same Title')->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertNotEquals($slugs[0], $slugs[1]);
    }

    public function test_guest_cannot_create_a_resource(): void
    {
        $this->post(route('resources.store'), ['title' => 'X', 'visibility' => 'public'])
            ->assertRedirect(route('login'));
    }

    public function test_a_restricted_user_cannot_create_a_resource(): void
    {
        $user = User::factory()->create(['account_status' => 'restricted']);

        $this->actingAs($user)->post(route('resources.store'), [
            'title' => 'X', 'visibility' => 'private',
        ])->assertForbidden();

        $this->assertDatabaseMissing('resources', ['title' => 'X']);
    }
}
