<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_conversation_creates_it_with_both_participants(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $response = $this->actingAs($a)->post(route('messages.start'), ['user_id' => $b->id]);

        $conversation = Conversation::first();
        $response->assertRedirect(route('messages.show', $conversation));
        $this->assertSame('direct', $conversation->type);
        $this->assertCount(2, $conversation->participants);
        $this->assertTrue($conversation->participants->pluck('user_id')->contains($a->id));
        $this->assertTrue($conversation->participants->pluck('user_id')->contains($b->id));
    }

    public function test_starting_a_conversation_twice_reuses_the_existing_one(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('messages.start'), ['user_id' => $b->id]);
        $this->actingAs($b)->post(route('messages.start'), ['user_id' => $a->id]);

        $this->assertSame(1, Conversation::count());
    }

    public function test_a_user_cannot_start_a_conversation_with_themselves(): void
    {
        $a = User::factory()->create();

        $this->actingAs($a)->post(route('messages.start'), ['user_id' => $a->id])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, Conversation::count());
    }

    public function test_a_restricted_user_cannot_start_a_new_conversation(): void
    {
        $a = User::factory()->create(['account_status' => 'restricted']);
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('messages.start'), ['user_id' => $b->id])
            ->assertForbidden();

        $this->assertSame(0, Conversation::count());
    }

    public function test_duplicate_participant_rows_are_prevented_at_the_database_level(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $conversation->participants()->create(['user_id' => $a->id]);
    }

    public function test_conversation_listing_only_shows_the_users_own_conversations(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();
        Conversation::findOrCreateDirect($a, $b);
        Conversation::findOrCreateDirect($b, $c);

        $response = $this->actingAs($a)->get(route('messages.index'));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('conversations'));
    }

    public function test_participants_are_retrievable_from_a_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->assertSame($b->id, $conversation->otherParticipant($a)->user_id);
        $this->assertSame($a->id, $conversation->otherParticipant($b)->user_id);
    }
}
