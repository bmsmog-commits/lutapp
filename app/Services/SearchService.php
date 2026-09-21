<?php

namespace App\Services;

use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The single entry point for every search surface in the app — the global
 * "Search Lutapp..." box and each type-specific tab both go through this,
 * never a bespoke query built in a controller. Adding a new searchable
 * module means registering one more SearchProviderContract here, nothing else.
 */
class SearchService
{
    /** @var array<string, SearchProviderContract> */
    private array $providers = [];

    /**
     * @param  SearchProviderContract[]  $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function providerKeys(): array
    {
        return array_keys($this->providers);
    }

    public function provider(string $key): ?SearchProviderContract
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * A capped preview per type, for the unified "All" view.
     *
     * @return array<string, Collection<int, SearchResult>>
     */
    public function globalSearch(string $query, ?User $viewer, int $perType = 5): array
    {
        $results = [];

        foreach ($this->providers as $key => $provider) {
            if (mb_strlen(trim($query)) < 2) {
                $results[$key] = collect();

                continue;
            }

            // A small overfetch before ranking/slicing — cheap at this limit,
            // and lets exact/starts-with matches win the visible slots even
            // when a plain "contains" match happens to be fetched first.
            $models = $provider->buildQuery($query, $viewer, [])->limit($perType * 4)->get();
            $mapped = $models->map(fn ($model) => $provider->toResult($model));

            $results[$key] = $this->rank($mapped, $query)->take($perType)->values();
        }

        return $results;
    }

    /**
     * A fully paginated, filtered result set for one specific type.
     */
    public function searchType(string $key, string $query, ?User $viewer, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $provider = $this->provider($key);

        abort_unless($provider !== null, 404);

        $paginator = $provider->buildQuery($query, $viewer, $filters)->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(fn ($model) => $provider->toResult($model));

        return $paginator;
    }

    /**
     * Deterministic, explainable relevance: exact match, then starts-with,
     * then contains — never a subjective/AI ranking.
     */
    private function rank(Collection $results, string $query): Collection
    {
        $needle = mb_strtolower(trim($query));

        return $results->sortBy(function (SearchResult $result) use ($needle) {
            $title = mb_strtolower($result->title);

            return match (true) {
                $title === $needle => 0,
                str_starts_with($title, $needle) => 1,
                str_contains($title, $needle) => 2,
                default => 3,
            };
        })->values();
    }
}
