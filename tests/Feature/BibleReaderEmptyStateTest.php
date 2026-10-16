<?php

namespace Tests\Feature;

use App\Models\BibleBook;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 27.5 regression cover for the Bible reader empty state.
 *
 * A translation that has book/chapter structure but no imported verse text must
 * explain itself honestly — never a bare "No verses found", and never invented
 * verse text.
 */
class BibleReaderEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    private function genesis(): BibleBook
    {
        return BibleBook::create([
            'sort_order' => 1,
            'name' => 'Genesis',
            'abbreviation' => 'Gen',
            'testament' => 'Old',
            'chapters_count' => 50,
        ]);
    }

    private function kjv(): BibleTranslation
    {
        return BibleTranslation::create([
            'code' => 'KJV',
            'name' => 'King James Version',
            'language' => 'English',
            'license' => 'Public Domain',
            'public_domain' => true,
            'redistributable' => true,
            'is_active' => true,
        ]);
    }

    public function test_translation_without_imported_text_renders_the_explanatory_empty_state(): void
    {
        $user = User::factory()->create();
        $book = $this->genesis();
        $kjv = $this->kjv();

        BibleVerse::create([
            'bible_book_id' => $book->id,
            'translation_id' => $kjv->id,
            'chapter' => 1,
            'verse' => 1,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);

        $yoruba = BibleTranslation::create([
            'code' => 'YOR',
            'name' => 'Bibeli Mimo Inu (Yoruba Bible)',
            'language' => 'Yoruba',
            'license' => 'Unverified',
            'public_domain' => false,
            'redistributable' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('bible.index', [
            'book' => $book->id,
            'chapter' => 1,
            'translation_id' => $yoruba->id,
        ]));

        $response->assertOk();

        // The new empty state explains the situation instead of failing bare.
        $response->assertSee(__('messages.translation_text_not_imported_title'));
        $response->assertSee('Genesis 1');
        $response->assertSee('Bibeli Mimo Inu (Yoruba Bible)');

        // It must never fall back to the bare legacy message...
        $response->assertDontSee(__('messages.no_verses_found'));

        // ...and it must never show text from another translation as if it
        // belonged to this one.
        $response->assertDontSee('In the beginning God created the heaven and the earth.');

        // A non-redistributable translation is explicitly flagged as such.
        $response->assertSee(__('messages.translation_text_licensed_note'));

        // And there is a way back to a translation that really does have text.
        $response->assertSee(__('messages.translation_read_in', ['translation' => 'KJV']));
    }

    public function test_kjv_chapter_still_renders_real_verse_text(): void
    {
        $user = User::factory()->create();
        $book = $this->genesis();
        $kjv = $this->kjv();

        BibleVerse::create([
            'bible_book_id' => $book->id,
            'translation_id' => $kjv->id,
            'chapter' => 1,
            'verse' => 1,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);
        BibleVerse::create([
            'bible_book_id' => $book->id,
            'translation_id' => $kjv->id,
            'chapter' => 1,
            'verse' => 2,
            'text' => 'And the earth was without form, and void.',
        ]);

        $response = $this->actingAs($user)->get(route('bible.index', [
            'book' => $book->id,
            'chapter' => 1,
            'translation_id' => $kjv->id,
        ]));

        $response->assertOk();
        $response->assertSee('In the beginning God created the heaven and the earth.');
        $response->assertSee('And the earth was without form, and void.');
        $response->assertDontSee(__('messages.translation_text_not_imported_title'));
    }

    public function test_translation_selector_marks_availability_from_real_verse_rows(): void
    {
        $user = User::factory()->create();
        $book = $this->genesis();
        $kjv = $this->kjv();

        BibleVerse::create([
            'bible_book_id' => $book->id,
            'translation_id' => $kjv->id,
            'chapter' => 1,
            'verse' => 1,
            'text' => 'In the beginning God created the heaven and the earth.',
        ]);

        $empty = BibleTranslation::create([
            'code' => 'NIV',
            'name' => 'New International Version',
            'language' => 'English',
            'license' => 'Proprietary',
            'public_domain' => false,
            'redistributable' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($kjv->hasLocalText());
        $this->assertFalse($empty->hasLocalText());
        $this->assertSame('available', $kjv->availabilityState());
        $this->assertSame('licensed_unavailable', $empty->availabilityState());

        $response = $this->actingAs($user)->get(route('bible.index'));
        $response->assertOk();
        $response->assertSee(__('messages.translation_available'));
        $response->assertSee(__('messages.translation_not_available'));
    }
}
