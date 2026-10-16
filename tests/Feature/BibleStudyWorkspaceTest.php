<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BibleStudyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function kjv(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'KJV',
            'name' => 'King James Version',
            'language' => 'English',
            'license' => 'Public Domain',
            'license_type' => 'public_domain',
            'public_domain' => true,
            'redistributable' => true,
            'is_active' => true,
        ]);
    }

    private function genesis(): BibleBook
    {
        return BibleBook::create([
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'old',
            'chapters_count' => 50,
            'sort_order' => 1,
        ]);
    }

    public function test_split_screen_renders_both_panes_with_real_verse_text(): void
    {
        $user = User::factory()->create();
        $translation = $this->kjv();
        $book = $this->genesis();

        BibleVerse::create([
            'bible_book_id' => $book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $translation->id,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);

        $response = $this->actingAs($user)->get(route('bible.study', [
            'book' => $book->id,
            'chapter' => 1,
            'translation_id' => $translation->id,
        ]));

        $response->assertOk();
        $response->assertSee('In the beginning God created the heaven and the earth.');
        $response->assertSee('id="pane-bible"', false);
        $response->assertSee('id="pane-notes"', false);
        // The note editor posts to the EXISTING notes route — not a new system.
        $response->assertSee(route('notes.store'), false);
        // The cite button carries the reference only, never the verse text.
        $response->assertSee('data-reference="Genesis 1:1 (KJV)"', false);
    }

    public function test_saving_a_note_from_the_workspace_stores_the_bible_reference(): void
    {
        $user = User::factory()->create();
        $translation = $this->kjv();
        $book = $this->genesis();

        $response = $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Creation',
            'body' => "Genesis 1:1 (KJV) — worth sitting with.",
            'color' => '#fff7b2',
            'bible_reference' => 'Genesis 1 (KJV)',
            'bible_book_id' => $book->id,
            'bible_chapter' => 1,
            'bible_translation_id' => $translation->id,
            'redirect_to' => '/bible/study?book='.$book->id,
        ]);

        $response->assertRedirect('/bible/study?book='.$book->id);

        $note = Note::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Genesis 1 (KJV)', $note->bible_reference);
        $this->assertSame($book->id, $note->bible_book_id);
        $this->assertSame(1, (int) $note->bible_chapter);
        $this->assertSame($translation->id, $note->bible_translation_id);
        $this->assertTrue($note->hasBibleReference());
    }

    public function test_an_ordinary_note_still_saves_with_no_bible_reference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Groceries',
            'body' => 'Milk',
            'color' => '#fff7b2',
        ])->assertRedirect(route('notes.index'));

        $note = Note::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($note->bible_reference);
        $this->assertFalse($note->hasBibleReference());
    }

    public function test_redirect_to_only_honours_in_app_paths(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Note',
            'body' => 'Body',
            'color' => '#fff7b2',
            'redirect_to' => 'https://evil.example.com/steal',
        ])->assertRedirect(route('notes.index'));

        $this->actingAs($user)->post(route('notes.store'), [
            'title' => 'Note',
            'body' => 'Body',
            'color' => '#fff7b2',
            'redirect_to' => '//evil.example.com/steal',
        ])->assertRedirect(route('notes.index'));
    }

    public function test_workspace_shows_the_honest_empty_state_and_no_other_translation_text(): void
    {
        $user = User::factory()->create();
        $kjv = $this->kjv();
        $book = $this->genesis();

        BibleVerse::create([
            'bible_book_id' => $book->id,
            'chapter' => 1,
            'verse' => 1,
            'translation_id' => $kjv->id,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);

        $licensed = BibleTranslation::create([
            'code' => 'NIV',
            'name' => 'New International Version',
            'language' => 'English',
            'license' => 'Proprietary',
            'license_type' => 'licensed_remote',
            'availability_status' => 'license_required',
            'public_domain' => false,
            'redistributable' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('bible.study', [
            'book' => $book->id,
            'chapter' => 1,
            'translation_id' => $licensed->id,
        ]));

        $response->assertOk();
        $response->assertSee('bible-translation-unavailable', false);
        $response->assertSee(__('messages.translation_text_licensed_note'));
        // Critically: the KJV text must NOT leak into an unlicensed translation.
        $response->assertDontSee('In the beginning God created the heaven and the earth.');
    }
}
