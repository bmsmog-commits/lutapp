<?php

namespace App\Support\Search\Providers;

use App\Models\Job;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class JobSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'jobs';
    }

    public function label(): string
    {
        return 'Jobs';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Reuses Job::scopeVisibleTo() exactly as the job board's own index
        // does — draft/closed/cancelled and private-organization jobs are
        // excluded by that scope before search ever sees them.
        $builder = Job::query()->visibleTo($viewer)->with(['organization', 'user'])
            ->where(function (Builder $q) use ($query) {
                $q->where('title', 'like', "%{$query}%")->orWhere('description', 'like', "%{$query}%");
            });

        if ($category = $filters['category'] ?? null) {
            $builder->where('category', $category);
        }

        if ($workMode = $filters['work_mode'] ?? null) {
            $builder->where('work_mode', $workMode);
        }

        if ($organizationId = $filters['organization_id'] ?? null) {
            $builder->where('organization_id', $organizationId);
        }

        if ($city = $filters['city'] ?? null) {
            $builder->where('city', 'like', "%{$city}%");
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var Job $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->title,
            subtitle: $model->organization?->name ?? $model->user?->name,
            description: $model->city ?: ucfirst(str_replace('_', ' ', $model->work_mode)),
            url: route('jobs.show', $model),
            image: null,
            category: $model->category,
        );
    }
}
