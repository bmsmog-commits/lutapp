<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithProfile(array $userOverrides = [], array $profileOverrides = []): User
    {
        $user = User::factory()->create($userOverrides);
        $user->profile()->create(array_merge([
            'username' => 'user'.$user->id,
            'display_name' => $user->name,
        ], $profileOverrides));

        return $user->fresh();
    }

    // FOLLOW / UNFOLLOW

    public function test_user_can_follow_an_eligible_user(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile(['name' => 'Followable'], ['username' => 'followable']);

        $this->actingAs($follower)->post(route('users.follow', 'followable'))->assertRedirect();

        $this->assertDatabaseHas('user_connections', ['follower_id' => $follower->id, 'following_id' => $target->id]);
    }

    public function test_user_can_unfollow(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'unfollowtarget']);
        $this->actingAs($follower)->post(route('users.follow', 'unfollowtarget'));

        $this->actingAs($follower)->delete(route('users.unfollow', 'unfollowtarget'))->assertRedirect();

        $this->assertDatabaseMissing('user_connections', ['follower_id' => $follower->id, 'following_id' => $target->id]);
    }

    public function test_duplicate_follow_is_idempotent_and_does_not_create_a_second_row(): void
    {
        $follower = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'duptarget']);

        $this->actingAs($follower)->post(route('users.follow', 'duptarget'));
        $this->actingAs($follower)->post(route('users.follow', 'duptarget'));

        $this->assertSame(1, UserConnection::where('follower_id', $follower->id)->count());
    }

    public function test_self_follow_is_prevented(): void
    {
        $user = $this->createUserWithProfile([], ['username' => 'selffollow']);

        $this->actingAs($user)->post(route('users.follow', 'selffollow'))->assertSessionHasErrors('follow');

        $this->assertSame(0, UserConnection::count());
    }

    public function test_following_a_nonexistent_username_404s(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('users.follow', 'no-such-user'))->assertNotFound();
    }

    public function test_unauthenticated_follow_attempt_redirects_to_login(): void
    {
        $this->createUserWithProfile([], ['username' => 'guestfollow']);

        $this->post(route('users.follow', 'guestfollow'))->assertRedirect(route('login'));
    }

    public function test_follower_and_following_counts_are_correct(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'counttarget']);
        $this->actingAs($a)->post(route('users.follow', 'counttarget'));
        $this->actingAs($b)->post(route('users.follow', 'counttarget'));

        $this->assertSame(2, $target->followers()->count());
        $this->assertSame(0, $target->following()->count());
        $this->assertSame(1, $a->following()->count());
    }

    public function test_unfollowing_is_idempotent(): void
    {
        $follower = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'idempotentunfollow']);

        $this->actingAs($follower)->delete(route('users.unfollow', 'idempotentunfollow'))->assertRedirect();
        $this->actingAs($follower)->delete(route('users.unfollow', 'idempotentunfollow'))->assertRedirect();

        $this->assertSame(0, UserConnection::count());
    }

    // PRIVACY

    public function test_private_profile_cannot_be_followed(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'privatetarget']);
        $target->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        // The profile page itself 404s, so the follow route (which resolves
        // the same username) does too — nothing to follow if it can't be found.
        $this->actingAs($follower)->post(route('users.follow', 'privatetarget'))->assertNotFound();

        $this->assertSame(0, UserConnection::count());
    }

    public function test_public_profile_remains_followable(): void
    {
        $follower = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'stillpublic']);

        $this->actingAs($follower)->post(route('users.follow', 'stillpublic'))->assertRedirect();

        $this->assertSame(1, UserConnection::count());
    }

    // FOLLOWERS / FOLLOWING LISTS

    public function test_followers_list_is_paginated(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'popularperson']);
        foreach (range(1, 35) as $i) {
            $follower = User::factory()->create();
            $follower->following()->attach($target->id);
        }

        $response = $this->get(route('users.followers', 'popularperson'));

        $response->assertOk();
        $this->assertSame(30, $response->viewData('followers')->count());
        $this->assertTrue($response->viewData('followers')->hasMorePages());
    }

    public function test_following_list_is_paginated(): void
    {
        $follower = $this->createUserWithProfile([], ['username' => 'followsalot']);
        foreach (range(1, 35) as $i) {
            $target = User::factory()->create();
            $follower->following()->attach($target->id);
        }

        $response = $this->get(route('users.following', 'followsalot'));

        $response->assertOk();
        $this->assertSame(30, $response->viewData('following')->count());
    }

    public function test_followers_list_of_a_private_profile_is_not_accessible(): void
    {
        $target = $this->createUserWithProfile([], ['username' => 'privatefollowerslist']);
        $target->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('users.followers', 'privatefollowerslist'))->assertNotFound();
    }

    // BLOCKING

    public function test_user_can_block_another_user(): void
    {
        $blocker = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'blocktarget']);

        $this->actingAs($blocker)->post(route('users.block', 'blocktarget'))->assertRedirect();

        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $blocker->id, 'blocked_id' => $target->id]);
    }

    public function test_blocking_removes_an_existing_follow_relationship_both_ways(): void
    {
        $a = $this->createUserWithProfile([], ['username' => 'blockera']);
        $b = $this->createUserWithProfile([], ['username' => 'blockerb']);
        $this->actingAs($a)->post(route('users.follow', 'blockerb'));
        $this->actingAs($b)->post(route('users.follow', 'blockera'));
        $this->assertSame(2, UserConnection::count());

        $this->actingAs($a)->post(route('users.block', 'blockerb'));

        $this->assertSame(0, UserConnection::count());
    }

    public function test_a_blocked_user_cannot_initiate_a_follow_via_direct_request(): void
    {
        $blocker = $this->createUserWithProfile([], ['username' => 'directblocker']);
        $blockedUser = $this->createUserWithProfile([], ['username' => 'directblocked']);
        $this->actingAs($blocker)->post(route('users.block', 'directblocked'));

        // The blocked user attempts to follow the blocker directly.
        $this->actingAs($blockedUser)->post(route('users.follow', 'directblocker'))->assertSessionHasErrors('follow');

        $this->assertSame(0, UserConnection::count());
    }

    public function test_block_cannot_be_bypassed_by_the_blocker_following_again(): void
    {
        $blocker = $this->createUserWithProfile([], ['username' => 'rebindblocker']);
        $target = $this->createUserWithProfile([], ['username' => 'rebindtarget']);
        $this->actingAs($blocker)->post(route('users.block', 'rebindtarget'));

        $this->actingAs($blocker)->post(route('users.follow', 'rebindtarget'))->assertSessionHasErrors('follow');

        $this->assertSame(0, UserConnection::count());
    }

    public function test_user_can_unblock(): void
    {
        $blocker = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'unblocktarget']);
        $this->actingAs($blocker)->post(route('users.block', 'unblocktarget'));

        $this->actingAs($blocker)->delete(route('users.unblock', 'unblocktarget'))->assertRedirect();

        $this->assertSame(0, UserBlock::count());
    }

    public function test_self_block_is_prevented(): void
    {
        $user = $this->createUserWithProfile([], ['username' => 'selfblock']);

        $this->actingAs($user)->post(route('users.block', 'selfblock'))->assertSessionHasErrors('block');

        $this->assertSame(0, UserBlock::count());
    }

    public function test_blocking_does_not_delete_existing_conversation_history(): void
    {
        $a = User::factory()->create();
        $b = $this->createUserWithProfile([], ['username' => 'historykept']);
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $conversation->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'hello before block']);

        $this->actingAs($a)->post(route('users.block', 'historykept'));

        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'body' => 'hello before block']);
        $this->actingAs($a)->get(route('messages.show', $conversation))->assertOk()->assertSee('hello before block');
    }

    // MESSAGING INTEGRATION

    public function test_blocked_user_cannot_start_a_new_conversation_with_the_blocker(): void
    {
        $blocker = User::factory()->create();
        $blocked = $this->createUserWithProfile([], ['username' => 'msgblocked']);
        $this->actingAs($blocker)->post(route('users.block', 'msgblocked'));

        $this->actingAs($blocked)->post(route('messages.start'), ['user_id' => $blocker->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, Conversation::count());
    }

    public function test_blocked_user_cannot_send_a_new_message_into_an_existing_conversation(): void
    {
        $blocker = User::factory()->create();
        $blocked = $this->createUserWithProfile([], ['username' => 'msgblocked2']);
        $conversation = Conversation::findOrCreateDirect($blocker, $blocked);
        $this->actingAs($blocker)->post(route('users.block', 'msgblocked2'));

        $this->actingAs($blocked)->post(route('messages.messages.store', $conversation), ['body' => 'still trying'])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('messages', ['body' => 'still trying']);
    }

    public function test_existing_unrelated_conversations_remain_unaffected_by_a_block(): void
    {
        $a = User::factory()->create();
        $b = $this->createUserWithProfile([], ['username' => 'unaffected']);
        $c = User::factory()->create();
        $conversationAC = Conversation::findOrCreateDirect($a, $c);

        $this->actingAs($a)->post(route('users.block', 'unaffected'));

        $this->actingAs($a)->post(route('messages.messages.store', $conversationAC), ['body' => 'still fine'])->assertRedirect();
        $this->assertDatabaseHas('messages', ['body' => 'still fine']);
    }

    // NOTIFICATIONS

    public function test_follow_creates_a_notification_for_the_target(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'notifytarget']);

        $this->actingAs($follower)->post(route('users.follow', 'notifytarget'));

        $this->assertDatabaseHas('app_notifications', ['user_id' => $target->id, 'actor_id' => $follower->id, 'type' => 'user.followed']);
    }

    public function test_duplicate_follow_action_does_not_create_a_duplicate_notification(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'nodupnotify']);

        $this->actingAs($follower)->post(route('users.follow', 'nodupnotify'));
        $this->actingAs($follower)->post(route('users.follow', 'nodupnotify'));

        $this->assertSame(1, Notification::where('type', 'user.followed')->count());
    }

    public function test_refollowing_after_unfollow_creates_a_new_notification(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'refollownotify']);
        $this->actingAs($follower)->post(route('users.follow', 'refollownotify'));
        $this->actingAs($follower)->delete(route('users.unfollow', 'refollownotify'));

        $this->actingAs($follower)->post(route('users.follow', 'refollownotify'));

        $this->assertSame(2, Notification::where('type', 'user.followed')->count());
    }

    public function test_connections_notification_preference_is_respected(): void
    {
        $follower = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'preftarget']);
        $target->preferences()->create(['notification_preferences' => ['connections' => false]]);

        $this->actingAs($follower)->post(route('users.follow', 'preftarget'));

        $this->assertSame(0, Notification::where('user_id', $target->id)->count());
    }

    // SEARCH

    public function test_search_shows_following_state_for_authenticated_viewer(): void
    {
        $viewer = User::factory()->create();
        $target = $this->createUserWithProfile(['name' => 'Search Following Target'], ['username' => 'searchfollowingtarget']);
        $this->actingAs($viewer)->post(route('users.follow', 'searchfollowingtarget'));

        $response = $this->actingAs($viewer)->get(route('search.index', ['q' => 'Search Following Target', 'type' => 'people']));

        $response->assertSee('Following');
    }

    public function test_search_does_not_expose_another_viewers_relationship_state(): void
    {
        $viewerA = User::factory()->create();
        $viewerB = User::factory()->create();
        $target = $this->createUserWithProfile(['name' => 'Neutral Search Target'], ['username' => 'neutralsearchtarget']);
        $this->actingAs($viewerA)->post(route('users.follow', 'neutralsearchtarget'));

        $response = $this->actingAs($viewerB)->get(route('search.index', ['q' => 'Neutral Search Target', 'type' => 'people']));

        $response->assertDontSee('Following');
    }

    public function test_public_discovery_still_excludes_private_users(): void
    {
        $target = $this->createUserWithProfile(['name' => 'Still Private Search'], ['username' => 'stillprivatesearch']);
        $target->preferences()->create(['privacy_preferences' => ['discoverable' => false]]);

        $response = $this->get(route('search.index', ['q' => 'Still Private Search', 'type' => 'people']));

        $this->assertCount(0, $response->viewData('results'));
    }

    // PROFILE INTEGRATION

    public function test_profile_shows_correct_follow_button_state(): void
    {
        $viewer = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'buttonstate']);

        $response = $this->actingAs($viewer)->get(route('users.show', 'buttonstate'));

        $response->assertSee('Follow');
        $this->assertFalse($response->viewData('isFollowing'));

        $this->actingAs($viewer)->post(route('users.follow', 'buttonstate'));
        $response = $this->actingAs($viewer)->get(route('users.show', 'buttonstate'));

        $response->assertSee('Following');
        $this->assertTrue($response->viewData('isFollowing'));
    }

    public function test_profile_shows_correct_follower_and_following_counts(): void
    {
        $follower = User::factory()->create();
        $this->createUserWithProfile([], ['username' => 'countsdisplay']);
        $this->actingAs($follower)->post(route('users.follow', 'countsdisplay'));

        $response = $this->get(route('users.show', 'countsdisplay'));

        $this->assertSame(1, $response->viewData('followersCount'));
        $this->assertSame(0, $response->viewData('followingCount'));
    }

    // SECURITY

    public function test_user_cannot_delete_another_users_follow_relationship(): void
    {
        $a = User::factory()->create();
        $b = $this->createUserWithProfile([], ['username' => 'protectedtarget']);
        $this->actingAs($a)->post(route('users.follow', 'protectedtarget'));

        // A different, unrelated user attempts to remove A's follow of B by
        // calling unfollow on B's username while authenticated as themselves
        // — this can only ever remove THEIR OWN relationship (a no-op here,
        // since they were never following B), never A's.
        $attacker = User::factory()->create();
        $this->actingAs($attacker)->delete(route('users.unfollow', 'protectedtarget'));

        $this->assertDatabaseHas('user_connections', ['follower_id' => $a->id, 'following_id' => $b->id]);
    }

    public function test_connections_remain_global_not_organization_scoped(): void
    {
        $ownerA = User::factory()->create();
        \App\Models\Organization::create(['owner_id' => $ownerA->id, 'name' => 'Org A', 'slug' => 'conn-org-a', 'type' => 'church', 'visibility' => 'public']);
        $ownerB = User::factory()->create();
        $target = $this->createUserWithProfile([], ['username' => 'crossorgtarget']);
        \App\Models\Organization::create(['owner_id' => $ownerB->id, 'name' => 'Org B', 'slug' => 'conn-org-b', 'type' => 'church', 'visibility' => 'public']);

        // ownerA and target belong to entirely unrelated organizations —
        // nothing about organization membership should block or require
        // anything for a plain user-to-user follow.
        $this->actingAs($ownerA)->post(route('users.follow', 'crossorgtarget'))->assertRedirect();

        $this->assertDatabaseHas('user_connections', ['follower_id' => $ownerA->id, 'following_id' => $target->id]);
    }

    // RATE LIMITING

    public function test_follow_endpoint_is_rate_limited(): void
    {
        $follower = User::factory()->create();
        $targets = collect(range(1, 31))->map(fn ($i) => $this->createUserWithProfile([], ['username' => "ratelimit{$i}"]));

        $lastResponse = null;
        foreach ($targets as $target) {
            $lastResponse = $this->actingAs($follower)->post(route('users.follow', $target->profile->username));
        }

        $lastResponse->assertStatus(429);
    }
}
