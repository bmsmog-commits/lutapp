<?php

namespace App\Support\Search;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A future searchable module implements this and is registered in
 * SearchService — nothing else needs to change. Every provider is
 * responsible for its OWN authorization: buildQuery() must apply the exact
 * same visibility scope the module's own browsing routes already use
 * (Resource::scopeVisibleTo(), Organization::scopeInPublicDirectory(), etc.)
 * so search can never surface something the viewer couldn't otherwise reach.
 */
interface SearchProviderContract
{
    // Stable identifier used in ?type=... and as the result-group key.
    public function key(): string;

    public function label(): string;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function buildQuery(string $query, ?User $viewer, array $filters): Builder;

    public function toResult(Model $model): SearchResult;
}
