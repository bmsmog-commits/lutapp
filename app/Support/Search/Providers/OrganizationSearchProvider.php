<?php

namespace App\Support\Search\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'organizations';
    }

    public function label(): string
    {
        return 'Organizations';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Reuses the exact Phase 14 directory scope — never a second
        // visibility rule for organizations.
        $builder = Organization::query()->inPublicDirectory()->with('logo')
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%")
                    ->orWhere('state', 'like', "%{$query}%")
                    ->orWhere('country', 'like', "%{$query}%");
            });

        if ($type = $filters['type'] ?? null) {
            $builder->where('type', $type);
        }

        foreach (['country', 'state', 'city'] as $field) {
            if ($value = $filters[$field] ?? null) {
                $builder->where($field, $value);
            }
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var Organization $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->name,
            subtitle: ucfirst($model->type),
            description: collect([$model->city, $model->state, $model->country])->filter()->join(', ') ?: null,
            url: route('directory.show', $model),
            image: $model->logo ? route('files.show', $model->logo) : null,
            category: $model->type,
        );
    }
}
