<?php

namespace App\Http\Controllers;

use App\Models\BibleBook;
use App\Models\BibleBookmark;
use App\Models\BibleHighlight;
use App\Models\BibleReadingHistory;
use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Services\Bible\ApiBible\ApiBibleClient;
use App\Services\Bible\ApiBible\ApiBibleException;
use App\Services\Bible\ApiBible\RemoteSearchHit;
use App\Services\Bible\ApiBible\RemoteSearchResult;
use App\Services\Bible\ApiBible\UsfmBooks;
use App\Services\PreferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BibleController extends Controller
{
    // Phase 27.9 — API.Bible search page size (one API call per page).
    private const REMOTE_SEARCH_PER_PAGE = 25;

    /**
     * Display the Bible reader with verses, translation switcher, and user features
     */
    public function index(Request $request, PreferenceService $preferences): View
    {
        return view('bible.index', $this->readerContext($request, $preferences));
    }

    /**
     * Phase 27.6 — the Bible Study split-screen workspace.
     *
     * Same reader context as index(), plus the user's existing notes. The note
     * editor posts to the EXISTING notes.store / notes.update routes — there is
     * no parallel note system here, only a second surface onto the mature one.
     */
    public function study(Request $request, PreferenceService $preferences): View
    {
        $context = $this->readerContext($request, $preferences);

        $context['studyNotes'] = $request->user()
            ->notes()
            ->whereNull('passcode_hash') // locked notes are never rendered here
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $context['noteColors'] = ['#fff7b2', '#d7f9d2', '#d7ecff', '#ffd8d8', '#f2ddff', '#ffffff'];

        return view('bible.study', $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function readerContext(Request $request, PreferenceService $preferences): array
    {
        $translations = BibleTranslation::where('is_active', true)->orderBy('name')->get();

        // Phase 27.5 — honest availability per translation: a translation is
        // "available" only if real verse rows for it exist locally. One grouped
        // query, no per-row N+1, and nothing is inferred or fabricated.
        $verseCounts = BibleVerse::query()
            ->selectRaw('translation_id, count(*) as aggregate')
            ->groupBy('translation_id')
            ->pluck('aggregate', 'translation_id');

        $availability = $translations->mapWithKeys(fn (BibleTranslation $t) => [
            $t->id => [
                'has_text' => ($verseCounts[$t->id] ?? 0) > 0,
                'verse_count' => (int) ($verseCounts[$t->id] ?? 0),
                'state' => $t->availabilityState(($verseCounts[$t->id] ?? 0) > 0),
                'remote' => ($verseCounts[$t->id] ?? 0) === 0 && $t->canBeServedRemotely(),
            ],
        ]);

        // Phase 27.9 — while a 429 cooldown is in force, every remote
        // translation is honestly "temporarily unavailable" (runtime only).
        if (app(ApiBibleClient::class)->isCoolingDown()) {
            $availability = $availability->map(fn (array $info) => $info['remote']
                ? array_merge($info, ['state' => 'temporarily_unavailable', 'remote' => false])
                : $info);
        }

        // Get translation ID from request, else the user's saved preference
        // (Phase 21), else (Phase 27.9) the first translation with local
        // text — so a user with no preference lands on KJV rather than on
        // whichever remote or empty translation sorts first alphabetically.
        $translationId = $request->integer('translation_id');
        if (!$translationId && $request->user()) {
            $preferredId = $preferences->bible($request->user())['translation_id'];
            if ($preferredId && $translations->contains('id', $preferredId)) {
                $translationId = $preferredId;
            }
        }
        if (!$translationId) {
            $translationId = $this->defaultTranslationId($translations, $verseCounts);
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
        $remoteChapter = null;
        $remoteError = null;

        if ($currentBook) {
            $currentChapter = min($currentChapter, $currentBook->chapters_count);
            
            // Get verses for the selected translation
            $verses = BibleVerse::where('bible_book_id', $currentBook->id)
                ->where('chapter', $currentChapter)
                ->where('translation_id', $translationId)
                ->orderBy('verse')
                ->get();

            // Phase 27.8 — local text always wins (the KJV corpus is never
            // shadowed). Only a translation with no local verses and a verified
            // API.Bible ID is fetched remotely; the text is displayed, never
            // stored in bible_verses.
            if ($verses->isEmpty() && $selectedTranslation->canBeServedRemotely()) {
                $usfm = UsfmBooks::forSortOrder((int) $currentBook->sort_order);

                try {
                    if ($usfm === null) {
                        throw new ApiBibleException(ApiBibleException::NOT_FOUND);
                    }

                    $remoteChapter = app(ApiBibleClient::class)
                        ->chapter($selectedTranslation->api_bible_id, $usfm, $currentChapter);
                    $verses = $remoteChapter->verses;
                } catch (ApiBibleException $e) {
                    $remoteError = $e->reason;
                }
            }

            // Phase 27.9 — a failed request changes only what the selector
            // says for THIS request. Nothing is persisted: a network blip or
            // 429 never turns into a stored licensing restriction.
            if ($remoteError !== null && isset($availability[$selectedTranslation->id])) {
                $runtimeState = $this->runtimeStateFor($remoteError);
                if ($runtimeState !== null) {
                    $availability->put($selectedTranslation->id, array_merge(
                        $availability[$selectedTranslation->id],
                        ['state' => $runtimeState, 'remote' => false]
                    ));
                }
            }

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

                // Record reading history (Phase 27.6: through the model's
                // canonical writer, which stamps the 24-hour expiry).
                BibleReadingHistory::record(
                    (int) auth()->id(),
                    (int) $currentBook->id,
                    (int) $currentChapter,
                    (int) $translationId
                );
            }
        }

        // The fallback translation offered by the empty state: the first active
        // translation that actually has local text (KJV in a stock install).
        $fallbackTranslation = $translations->first(
            fn (BibleTranslation $t) => ($availability[$t->id]['has_text'] ?? false) && $t->id !== $selectedTranslation->id
        );

        // Phase 27.6 — "Continue reading": only non-expired (<24h) entries.
        $continueReading = $request->user()
            ? $request->user()->bibleReadingHistory()
                ->notExpired()
                ->with('book', 'translation')
                ->orderByDesc('last_read_at')
                ->limit(5)
                ->get()
            : collect();

        return [
            'translations' => $translations,
            'continueReading' => $continueReading,
            'availability' => $availability,
            'fallbackTranslation' => $fallbackTranslation,
            'selectedTranslation' => $selectedTranslation,
            'books' => $books,
            'currentBook' => $currentBook,
            'currentChapter' => $currentChapter,
            'verses' => $verses,
            'remoteChapter' => $remoteChapter,
            'remoteError' => $remoteError,
            'userBookmarks' => $userBookmarks,
            'userHighlights' => $userHighlights,
            'bibleFontSize' => $bibleFontSize,
        ];
    }

    /**
     * Search Bible verses across all translations
     */
    public function search(Request $request): View
    {
        // Search runs only on form submission (a GET per submit, never per
        // keystroke); the query is capped so it cannot be abused remotely.
        $query = Str::of($request->string('q')->trim())->limit(100, '')->toString();
        $translationId = $request->integer('translation_id');
        $translations = BibleTranslation::where('is_active', true)->orderBy('name')->get();
        $localIds = BibleVerse::query()->distinct()->pluck('translation_id')->map(fn ($id) => (int) $id);

        if (!$translationId) {
            $translationId = $translations->first(fn (BibleTranslation $t) => $localIds->contains($t->id))->id
                ?? $translations->first()->id ?? 1;
        }

        $translation = BibleTranslation::findOrFail($translationId);

        // Phase 27.9 — say plainly WHICH text is being searched.
        $mode = match (true) {
            $localIds->contains($translation->id) => 'local',
            $translation->canBeServedRemotely() => 'remote',
            default => 'unsupported',
        };

        $verses = collect();
        $remoteResult = null;
        $remoteError = null;
        $remoteCopyright = null;
        $page = max(1, $request->integer('page', 1));
        $books = BibleBook::orderBy('sort_order')->get()->keyBy('sort_order');

        if (mb_strlen($query) >= 3 && $mode === 'local') {
            $verses = BibleVerse::where('translation_id', $translationId)
                ->where('text', 'LIKE', "%{$query}%")
                ->with('book')
                ->orderBy('bible_book_id')
                ->orderBy('chapter')
                ->orderBy('verse')
                ->limit(100)
                ->get();
        } elseif (mb_strlen($query) >= 3 && $mode === 'remote') {
            $client = app(ApiBibleClient::class);

            try {
                $remoteResult = $client->search(
                    $translation->api_bible_id,
                    $query,
                    ($page - 1) * self::REMOTE_SEARCH_PER_PAGE,
                    self::REMOTE_SEARCH_PER_PAGE
                );
                $verses = $this->remoteHitsAsVerses($remoteResult, $translation, $books);
            } catch (ApiBibleException $e) {
                $remoteError = $e->reason;
            }

            if ($remoteResult !== null) {
                try {
                    $remoteCopyright = $client->copyright($translation->api_bible_id);
                } catch (ApiBibleException) {
                    // Attribution falls back to the stored licence below;
                    // a metadata hiccup never hides genuine results.
                    $remoteCopyright = null;
                }
            }
        }

        return view('bible.search', [
            'query' => $query,
            'verses' => $verses,
            'translation' => $translation,
            'mode' => $mode,
            'remoteResult' => $remoteResult,
            'remoteError' => $remoteError,
            'remoteCopyright' => $remoteCopyright,
            'page' => $page,
            'books' => $books,
            'localTranslation' => $translations->first(
                fn (BibleTranslation $t) => $localIds->contains($t->id) && $t->id !== $translation->id
            ),
        ]);
    }

    /**
     * Map API.Bible search hits onto the shape the search view already
     * renders (bible_book_id / chapter / verse / translation_id / book /
     * text), so bookmarks and highlights work on remote results too.
     * Hits for books Lutapp does not model are dropped, never guessed.
     *
     * @return Collection<int, object>
     */
    private function remoteHitsAsVerses(RemoteSearchResult $result, BibleTranslation $translation, Collection $books): Collection
    {
        return $result->hits
            ->map(function (RemoteSearchHit $hit) use ($books, $translation) {
                $book = $books->get(UsfmBooks::sortOrderFor($hit->usfmBook));

                return $book ? (object) [
                    'bible_book_id' => $book->id,
                    'chapter' => $hit->chapter,
                    'verse' => $hit->verse,
                    'translation_id' => $translation->id,
                    'book' => $book,
                    'text' => $hit->text,
                ] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, BibleTranslation>  $translations
     * @param  Collection<int, int>  $verseCounts
     */
    private function defaultTranslationId(Collection $translations, Collection $verseCounts): int
    {
        return $translations->first(fn (BibleTranslation $t) => ($verseCounts[$t->id] ?? 0) > 0)->id
            ?? $translations->first()->id
            ?? 1;
    }

    /**
     * The selector state a failed API.Bible request implies for this request
     * only. 404 means "this chapter", not "this translation", so it does not
     * change the translation's state.
     */
    private function runtimeStateFor(string $reason): ?string
    {
        return match ($reason) {
            ApiBibleException::UNAVAILABLE, ApiBibleException::RATE_LIMITED => 'temporarily_unavailable',
            ApiBibleException::UNAUTHORIZED => 'not_in_account',
            default => null,
        };
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
        // Phase 27.6 — reading history is a 24-hour trail. Expired rows are
        // never surfaced, even if the scheduled prune has not run yet.
        // Bookmarks and highlights are a separate, permanent concept and are
        // deliberately unaffected.
        $history = auth()->user()
            ->bibleReadingHistory()
            ->notExpired()
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
