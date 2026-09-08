<?php

namespace Tests\Feature;

use App\Models\SecurityQuestion;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotePasscodeRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_security_answer_allows_a_note_passcode_reset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('notes.store'), [
                'title' => 'Private note',
                'body' => 'Secret body',
                'color' => '#fff7b2',
                'passcode' => 'old-passcode',
            ]);

        $note = Note::firstOrFail();

        SecurityQuestion::create([
            'user_id' => $user->id,
            'question' => "What is your uncle's name?",
            'answer_hash' => Hash::make('samuel'),
        ]);

        $recoveryResponse = $this->actingAs($user)
            ->post(route('notes.submit-recovery', $note), ['answer' => 'samuel']);

        $recoveryResponse->assertRedirect(route('notes.reset-passcode', $note));

        $this->actingAs($user)
            ->withSession(['note_recovery_verified' => [$note->id => true]])
            ->post(route('notes.reset-passcode.update', $note), [
                'passcode' => 'new-passcode',
                'passcode_confirmation' => 'new-passcode',
            ])
            ->assertRedirect(route('notes.index'));

        $this->assertTrue(Hash::check('new-passcode', $note->fresh()->passcode_hash));
    }
}
