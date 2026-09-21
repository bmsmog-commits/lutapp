<?php

namespace App\Support\Search\Providers;

use App\Models\Resource;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResourceSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'resources';
    }

    public function label(): string
    {
        return 'Books & Resources';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Reuses Resource::scopeVisibleTo() exactly as the library index does.
        $builder = Resource::query()->visibleTo($viewer)->with(['cover', 'language', 'organization', 'user'])
            ->where(function (Builder $q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('author', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });

        if ($category = $filters['category'] ?? null) {
            $builder->where('category', $category);
        }

        if ($languageId = $filters['language_id'] ?? null) {
            $builder->where('language_id', $languageId);
        }

        if ($organizationId = $filters['organization_id'] ?? null) {
            $builder->where('organization_id', $organizationId);
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var Resource $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->title,
            subtitle: $model->author,
            description: $model->language?->name,
            url: route('resources.show', $model),
            image: $model->cover ? route('files.show', $model->cover) : null,
            category: $model->category,
        );
    }
}
