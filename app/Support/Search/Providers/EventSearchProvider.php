<?php

namespace App\Support\Search\Providers;

use App\Models\OrganizationEvent;
use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EventSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return 'Events';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        // Reuses OrganizationEvent::scopeVisibleTo() exactly as the event
        // discovery index does.
        $builder = OrganizationEvent::query()->visibleTo($viewer)->with(['organization', 'cover'])
            ->where(function (Builder $q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhereHas('organization', fn (Builder $sub) => $sub->where('name', 'like', "%{$query}%"));
            });

        if ($category = $filters['category'] ?? null) {
            $builder->where('category', $category);
        }

        if ($locationMode = $filters['location_mode'] ?? null) {
            $builder->where('location_mode', $locationMode);
        }

        if ($organizationId = $filters['organization_id'] ?? null) {
            $builder->where('organization_id', $organizationId);
        }

        if ($date = $filters['date'] ?? null) {
            $builder->whereDate('starts_at', $date);
        }

        return $builder;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var OrganizationEvent $model */
        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $model->title,
            subtitle: $model->organization->name,
            description: $model->starts_at->format('M j, Y g:i A'),
            url: route('org-events.show', $model),
            image: $model->cover ? route('files.show', $model->cover) : null,
            category: $model->category,
        );
    }
}
