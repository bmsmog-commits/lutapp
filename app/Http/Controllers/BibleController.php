<?php

namespace App\Http\Controllers;

use App\Models\BibleBook;
use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Services\PreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BibleController extends Controller
{
    /**
     * Display the Bible reader with verses, translation switcher, and user features
     */
    public function index(Request $request, PreferenceService $preferences): View
    {
        $translations = BibleTranslation::where('is_active', true)->orderBy('name')->get();

        // Get translation ID from request, else the user's saved preference
        // (Phase 21), else the first available translation — unchanged
        // fallback order for anyone who has never set a preference.
        $translationId = $request->integer('translation_id');
        if (!$translationId && $request->user()) {
            $preferredId = $preferences->bible($request->user())['translation_id'];
            if ($preferredId && $translations->contains('id', $preferredId)) {
                $translationId = $preferredId;
            }
        }
        if (!$translationId) {
            $translationId = $translations->first()->id ?? 1;
        }

        $selectedTranslation = BibleTranslation::findOrFail($translationId);
        $bibleFontSize = $request->user() ? $preferences->bible($request->user())['font_size'] : 'medium';

        $books = BibleBook::orderBy('sort_order')->get();
        $currentBook = $request->filled('book')
            ? BibleBook::where('id', $request->integer('book'))->first()
            : null;

        $currentChapter = max(1, $request->integer('chapter', 1));
        $verses = collect();
        $userBookmarks = collect();
        $userHighlights = collect();

        if ($currentBook) {
            $currentChapter = min($currentChapter, $currentBook->chapters_count);
            
            // Get verses for the selected translation
            $verses = BibleVerse::where('bible_book_id', $currentBook->id)
                ->where('chapter', $currentChapter)
                ->where('translation_id', $translationId)
                ->orderBy('verse')
                ->get();

            // Get user bookmarks and highlights for this chapter
            if (auth()->check()) {
                $userBookmarks = BibleBookmark::where('user_id', auth()->id())
                    ->where('bible_book_id', $currentBook->id)
                    ->where('chapter', $currentChapter)
                    ->where('translation_id', $translationId)
                    ->get();

                $userHighlights = BibleHighlight::where('user_id', auth()->id())
                    ->where('bible_book_id', $currentBook->id)
                    ->where('chapter', $currentChapter)
                    ->where('translation_id', $translationId)
                    ->get();

                // Record reading history
                BibleReadingHistory::updateOrCreate(
                    [
                        'user_id' => auth()->id(),
                        'bible_book_id' => $currentBook->id,
                        'chapter' => $currentChapter,
                        'translation_id' => $translationId,
                    ],
                    ['last_read_at' => now()]
                );
            }
        }

        return view('bible.index', [
            'translations' => $translations,
            'selectedTranslation' => $selectedTranslation,
            'books' => $books,
            'currentBook' => $currentBook,
            'currentChapter' => $currentChapter,
            'verses' => $verses,
            'userBookmarks' => $userBookmarks,
            'userHighlights' => $userHighlights,
            'bibleFontSize' => $bibleFontSize,
        ]);
    }

    /**
     * Search Bible verses across all translations
     */
    public function search(Request $request): View
    {
        $query = $request->string('q')->trim();
        $translationId = $request->integer('translation_id');
        $translations = BibleTranslation::where('is_active', true)->orderBy('name')->get();

        if (!$translationId) {
            $translationId = $translations->first()->id ?? 1;
        }
        
        $translation = BibleTranslation::findOrFail($translationId);

        $verses = collect();

        if (strlen($query) >= 3) {
            $verses = BibleVerse::where('translation_id', $translationId)
                ->where('text', 'LIKE', "%{$query}%")
                ->with('book')
                ->orderBy('bible_book_id')
                ->orderBy('chapter')
                ->orderBy('verse')
                ->limit(100)
                ->get();
        }

        return view('bible.search', [
            'query' => $query,
            'verses' => $verses,
            'translation' => $translation,
        ]);
    }

    /**
     * Add a bookmark (favorite verse)
     */
    public function bookmark(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'bible_book_id' => 'required|exists:bible_books,id',
            'chapter' => 'required|numeric',
            'verse' => 'nullable|numeric',
            'translation_id' => 'required|exists:bible_translations,id',
            'note' => 'nullable|string|max:500',
        ]);

        $bookmark = BibleBookmark::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'bible_book_id' => $request->integer('bible_book_id'),
                'chapter' => $request->integer('chapter'),
                'verse' => $request->integer('verse'),
                'translation_id' => $request->integer('translation_id'),
            ],
            ['note' => $request->input('note')]
        );

        return $this->actionResponse($request, 'Verse bookmarked.');
    }

    /**
     * Remove a bookmark
     */
    public function removeBookmark(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'bible_book_id' => 'required|exists:bible_books,id',
            'chapter' => 'required|numeric',
            'verse' => 'nullable|numeric',
            'translation_id' => 'required|exists:bible_translations,id',
        ]);

        BibleBookmark::where('user_id', auth()->id())
            ->where('bible_book_id', $request->integer('bible_book_id'))
            ->where('chapter', $request->integer('chapter'))
            ->where('verse', $request->integer('verse'))
            ->where('translation_id', $request->integer('translation_id'))
            ->delete();

        return $this->actionResponse($request, 'Bookmark removed.');
    }

    /**
     * Add or update a highlight
     */
    public function highlight(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'bible_book_id' => 'required|exists:bible_books,id',
            'chapter' => 'required|numeric',
            'verse' => 'required|numeric',
            'translation_id' => 'required|exists:bible_translations,id',
            'color' => 'required|in:yellow,red,blue,green,orange,purple',
            'note' => 'nullable|string|max:500',
        ]);

        $highlight = BibleHighlight::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'bible_book_id' => $request->integer('bible_book_id'),
                'chapter' => $request->integer('chapter'),
                'verse' => $request->integer('verse'),
                'translation_id' => $request->integer('translation_id'),
            ],
            [
                'color' => $request->input('color'),
                'note' => $request->input('note'),
            ]
        );

        return $this->actionResponse($request, 'Verse highlighted.');
    }

    /**
     * Remove a highlight
     */
    public function removeHighlight(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'bible_book_id' => 'required|exists:bible_books,id',
            'chapter' => 'required|numeric',
            'verse' => 'required|numeric',
            'translation_id' => 'required|exists:bible_translations,id',
        ]);

        BibleHighlight::where('user_id', auth()->id())
            ->where('bible_book_id', $request->integer('bible_book_id'))
            ->where('chapter', $request->integer('chapter'))
            ->where('verse', $request->integer('verse'))
            ->where('translation_id', $request->integer('translation_id'))
            ->delete();

        return $this->actionResponse($request, 'Highlight removed.');
    }

    /**
     * Get user's bookmarks
     */
    public function bookmarks(): View
    {
        $bookmarks = auth()->user()
            ->bibleBookmarks()
            ->with('book', 'translation')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('bible.bookmarks', ['bookmarks' => $bookmarks]);
    }

    /**
     * Get reading history
     */
    public function readingHistory(): View
    {
        $history = auth()->user()
            ->bibleReadingHistory()
            ->with('book', 'translation')
            ->orderBy('last_read_at', 'desc')
            ->paginate(15);

        return view('bible.reading-history', ['history' => $history]);
    }

    private function actionResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('status', $message);
    }
}
