<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessagingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    // MESSAGES

    public function test_participant_can_send_a_text_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hello there'])
            ->assertRedirect(route('messages.show', $conversation));

        $message = Message::first();
        $this->assertSame('Hello there', $message->body);
        $this->assertSame('text', $message->type);
        $this->assertSame($a->id, $message->sender_id);
        $this->assertSame($conversation->id, $message->conversation_id);
    }

    public function test_participant_can_send_a_file_message_through_media_storage_service(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($a)->post(route('messages.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('messages.show', $conversation));

        $message = Message::first();
        $this->assertSame('file', $message->type);
        $this->assertNotNull($message->media_id);
        $this->assertDatabaseHas('media_files', ['id' => $message->media_id, 'user_id' => $a->id, 'visibility' => 'private']);
    }

    public function test_a_message_with_no_body_and_no_attachment_is_rejected(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($a)->post(route('messages.messages.store', $conversation), [])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Message::count());
    }

    public function test_non_participant_cannot_send_a_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($intruder)->post(route('messages.messages.store', $conversation), ['body' => 'Hi'])
            ->assertForbidden();

        $this->assertSame(0, Message::count());
    }

    // AUTHORIZATION / VIEWING

    public function test_participant_can_view_the_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($a)->get(route('messages.show', $conversation))->assertOk();
        $this->actingAs($b)->get(route('messages.show', $conversation))->assertOk();
    }

    public function test_non_participant_cannot_view_the_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($outsider)->get(route('messages.show', $conversation))->assertForbidden();
    }

    public function test_guest_cannot_view_any_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->get(route('messages.show', $conversation))->assertRedirect(route('login'));
    }

    // ID TAMPERING

    public function test_a_user_cannot_view_another_users_conversation_by_guessing_its_id(): void
    {
        $victimA = User::factory()->create();
        $victimB = User::factory()->create();
        $privateConversation = Conversation::findOrCreateDirect($victimA, $victimB);

        $attacker = User::factory()->create();
        $this->actingAs($attacker)->get(route('messages.show', $privateConversation->id))->assertForbidden();
        $this->actingAs($attacker)->post(route('messages.messages.store', $privateConversation->id), ['body' => 'x'])->assertForbidden();
    }

    public function test_a_user_cannot_modify_or_delete_another_users_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'Original']);

        $this->actingAs($b)->put(route('messages.messages.update', [$conversation, $message]), ['body' => 'Hijacked'])
            ->assertForbidden();
        $this->actingAs($b)->delete(route('messages.messages.destroy', [$conversation, $message]))
            ->assertForbidden();

        $this->assertSame('Original', $message->refresh()->body);
    }

    public function test_sender_can_edit_and_delete_their_own_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $message = $conversation->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'Original']);

        $this->actingAs($a)->put(route('messages.messages.update', [$conversation, $message]), ['body' => 'Edited'])
            ->assertRedirect();
        $this->assertSame('Edited', $message->refresh()->body);
        $this->assertNotNull($message->edited_at);

        $this->actingAs($a)->delete(route('messages.messages.destroy', [$conversation, $message]))->assertRedirect();
        $this->assertSoftDeleted($message);
    }

    public function test_a_message_cannot_be_attached_to_a_conversation_the_actor_does_not_belong_to_via_route_mismatch(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversationAB = Conversation::findOrCreateDirect($a, $b);
        $message = $conversationAB->messages()->create(['sender_id' => $a->id, 'type' => 'text', 'body' => 'Hi']);

        $c = User::factory()->create();
        $conversationAC = Conversation::findOrCreateDirect($a, $c);

        $this->actingAs($a)->put(route('messages.messages.update', [$conversationAC, $message]), ['body' => 'x'])
            ->assertNotFound();
    }

    // ATTACHMENTS

    public function test_participant_can_download_a_conversation_attachment(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ]);
        $message = Message::first();

        $this->actingAs($b)->get(route('files.show', $message->media_id))->assertOk();
    }

    public function test_non_participant_cannot_download_a_conversation_attachment(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ]);
        $message = Message::first();

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('files.show', $message->media_id))->assertForbidden();
    }

    public function test_guest_cannot_download_a_private_conversation_attachment(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ]);
        $message = Message::first();
        \Illuminate\Support\Facades\Auth::logout();

        $this->get(route('files.show', $message->media_id))->assertNotFound();
    }

    // READ STATE

    public function test_new_conversation_shows_as_unread_for_the_recipient(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hi']);

        $response = $this->actingAs($b)->get(route('messages.index'));
        $unread = $response->viewData('conversations')->firstWhere('id', $conversation->id)->unread_count;
        $this->assertSame(1, $unread);
    }

    public function test_opening_a_conversation_marks_it_as_read(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hi']);

        $this->actingAs($b)->get(route('messages.show', $conversation));

        $response = $this->actingAs($b)->get(route('messages.index'));
        $unread = $response->viewData('conversations')->firstWhere('id', $conversation->id)->unread_count;
        $this->assertSame(0, $unread);
    }

    public function test_sender_does_not_see_their_own_message_as_unread(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);
        $this->actingAs($a)->post(route('messages.messages.store', $conversation), ['body' => 'Hi']);

        $response = $this->actingAs($a)->get(route('messages.index'));
        $unread = $response->viewData('conversations')->firstWhere('id', $conversation->id)->unread_count;
        $this->assertSame(0, $unread);
    }

    // SEARCH

    public function test_user_search_finds_a_matching_user_by_name(): void
    {
        $a = User::factory()->create();
        $target = User::factory()->create(['name' => 'Unique Searchable Name']);

        $response = $this->actingAs($a)->get(route('messages.search', ['q' => 'Unique Searchable']));

        $response->assertOk()->assertSee('Unique Searchable Name');
    }

    public function test_user_search_excludes_the_current_user(): void
    {
        $a = User::factory()->create(['name' => 'Self Match Name']);

        $response = $this->actingAs($a)->get(route('messages.search', ['q' => 'Self Match']));

        $this->assertCount(0, $response->viewData('results'));
    }

    public function test_user_search_does_not_expose_unrelated_sensitive_fields(): void
    {
        $a = User::factory()->create();
        $target = User::factory()->create(['name' => 'Findable Person', 'password' => 'super-secret-hash']);

        $response = $this->actingAs($a)->get(route('messages.search', ['q' => 'Findable Person']));

        $response->assertDontSee('super-secret-hash');
    }

    // ORGANIZATION ISOLATION

    public function test_organization_membership_does_not_grant_access_to_an_unrelated_private_conversation(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => 'grace-chapel', 'type' => 'church', 'visibility' => 'public',
        ]);
        $member = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $member->id, 'status' => 'active']);

        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($a, $b);

        $this->actingAs($member)->get(route('messages.show', $conversation))->assertForbidden();
    }

    public function test_a_users_personal_conversation_remains_isolated_from_an_unrelated_organizations_members(): void
    {
        $ownerA = User::factory()->create();
        $orgA = Organization::create([
            'owner_id' => $ownerA->id, 'name' => 'Org A', 'slug' => 'org-a', 'type' => 'church', 'visibility' => 'public',
        ]);
        $memberA = User::factory()->create();
        OrganizationMember::create(['organization_id' => $orgA->id, 'user_id' => $memberA->id, 'status' => 'active']);

        $ownerB = User::factory()->create();
        $conversation = Conversation::findOrCreateDirect($ownerB, User::factory()->create());

        $this->actingAs($memberA)->get(route('messages.show', $conversation))->assertForbidden();
    }
}
