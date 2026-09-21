<?php

namespace App\Support\Search\Providers;

use App\Models\BibleTranslation;
use App\Models\BibleVerse;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BibleSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'bible';
    }

    public function label(): string
    {
        return 'Bible';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Same shape as BibleController::search() — only active (licensed for
        // display) translations, and the same minimum-length guard against a
        // full-table scan on a 1–2 character query. Not rewritten, just reused.
        $translationId = $filters['translation_id']
            ?? BibleTranslation::where('is_active', true)->orderBy('name')->value('id');

        $builder = BibleVerse::query()->with('book')->orderBy('bible_book_id')->orderBy('chapter')->orderBy('verse');

        if ($translationId) {
            $builder->where('translation_id', $translationId);
        }

        if ($bookId = $filters['book_id'] ?? null) {
            $builder->where('bible_book_id', $bookId);
        }

        if (mb_strlen($query) >= 3) {
            $builder->where('text', 'like', "%{$query}%");
        } else {
            $builder->whereRaw('1 = 0');
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var BibleVerse $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->book->name.' '.$model->chapter.':'.$model->verse,
            subtitle: null,
            description: Str::limit($model->text, 140),
            url: route('bible.index', ['book' => $model->bible_book_id, 'chapter' => $model->chapter, 'translation_id' => $model->translation_id]),
            image: null,
            category: null,
        );
    }
}
