<?php

namespace App\Support\Search\Providers;

use App\Models\AudioResource;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AudioSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'audio';
    }

    public function label(): string
    {
        return 'Audio';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Reuses AudioResource::scopeVisibleTo() exactly as the audio library
        // index does — draft/archived and private/organization-only audio
        // outside the viewer's membership are excluded before search sees them.
        $builder = AudioResource::query()->visibleTo($viewer)->with(['cover', 'language', 'organization', 'creatorUser'])
            ->where(function (Builder $q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('creator_name', 'like', "%{$query}%")
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

        if ($creator = $filters['creator'] ?? null) {
            $builder->where('creator_name', 'like', "%{$creator}%");
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var AudioResource $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->title,
            subtitle: $model->creatorDisplayName(),
            description: $model->category,
            url: route('audio.show', $model),
            image: $model->cover ? route('files.show', $model->cover) : null,
            category: $model->category,
        );
    }
}
