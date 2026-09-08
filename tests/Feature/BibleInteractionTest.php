<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleHighlight;
use App\Models\BibleTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibleInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_bible_actions_redirect_back_with_status(): void
    {
        $user = User::factory()->create();
        $translation = BibleTranslation::create([
            'code' => 'TEST',
            'name' => 'Test Translation',
            'language' => 'English',
            'is_active' => true,
        ]);
        $book = BibleBook::create([
            'sort_order' => 1,
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'Old',
            'chapters_count' => 1,
        ]);

        $response = $this->actingAs($user)->from(route('bible.index'))->post(route('bible.highlight'), [
            'bible_book_id' => $book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $translation->id,
            'color' => 'yellow',
        ]);

        $response->assertRedirect(route('bible.index'));
        $response->assertSessionHas('status', 'Verse highlighted.');
        $this->assertDatabaseHas('bible_highlights', [
            'user_id' => $user->id,
            'bible_book_id' => $book->id,
            'translation_id' => $translation->id,
            'color' => 'yellow',
        ]);
    }

    public function test_json_bible_actions_keep_an_api_response(): void
    {
        $user = User::factory()->create();
        $translation = BibleTranslation::create([
            'code' => 'TEST',
            'name' => 'Test Translation',
            'language' => 'English',
            'is_active' => true,
        ]);
        $book = BibleBook::create([
            'sort_order' => 1,
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'Old',
            'chapters_count' => 1,
        ]);

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->postJson(route('bible.bookmark'), [
                'bible_book_id' => $book->id,
                'chapter' => 1,
                'verse' => 1,
                'translation_id' => $translation->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
