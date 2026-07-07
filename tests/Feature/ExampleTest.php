<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_the_home_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_create_a_locked_note_and_unlock_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/notes', [
                'title' => 'Private note',
                'body' => 'Secret body',
                'color' => '#fff7b2',
                'passcode' => '1234',
            ])
            ->assertRedirect('/notes');

        $note = Note::firstOrFail();

        $this->assertTrue(Hash::check('1234', $note->passcode_hash));

        $this->actingAs($user)
            ->get('/notes')
            ->assertOk()
            ->assertSee('Private note')
            ->assertDontSee('Secret body');

        $this->actingAs($user)
            ->post("/notes/{$note->id}/unlock", ['passcode' => '1234'])
            ->assertRedirect('/notes');

        $this->actingAs($user)
            ->withSession(['unlocked_notes' => [$note->id => true]])
            ->get('/notes')
            ->assertOk()
            ->assertSee('Secret body');
    }
}
