<?php

namespace App\Support\Search\Providers;

use App\Models\User;
use App\Support\Search\SearchProviderContract;
use App\Support\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserSearchProvider implements SearchProviderContract
{
    public function key(): string
    {
        return 'people';
    }

    public function label(): string
    {
        return 'People';
    }

    public function buildQuery(string $query, ?User $viewer, array $filters): Builder
    {
        $base = User::query()
            // A public profile (Phase 22) requires a UserProfile row with a
            // username — nothing to search-result-link to otherwise.
            ->whereHas('profile')
            ->where(function (Builder $q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhereHas('profile', function (Builder $p) use ($query) {
                        $p->where('username', 'like', "%{$query}%")
                            ->orWhere('display_name', 'like', "%{$query}%")
                            ->orWhere('bio', 'like', "%{$query}%");
                    });
            })
            // Phase 21 privacy preference (now also the Phase 22 public-profile
            // gate — see PreferenceService::canViewPublicProfile()): a user who
            // turned off "appear in search"/"public profile" is excluded here.
            // No preferences row, or the flag explicitly true, both mean
            // discoverable (the safe default).
            ->where(function (Builder $q) {
                $q->whereDoesntHave('preferences')
                    ->orWhereHas('preferences', function (Builder $p) {
                        $p->whereNull('privacy_preferences')
                            ->orWhereJsonDoesntContain('privacy_preferences->discoverable', false);
                    });
            })
            // Phase 24: a suspended/deactivated account never appears in
            // search, regardless of its discoverable preference — moderation
            // is a stricter ceiling on top of it, not a substitute for it.
            ->whereNotIn('account_status', ['suspended', 'deactivated'])
            ->with('profile.profilePhoto');

        if ($viewer) {
            $base->where('id', '!=', $viewer->id);
        }

        if ($country = $filters['country'] ?? null) {
            $base->whereHas('profile', fn (Builder $p) => $p->where('country', $country));
        }

        if ($city = $filters['city'] ?? null) {
            $base->whereHas('profile', fn (Builder $p) => $p->where('city', 'like', "%{$city}%"));
        }

        if ($language = $filters['language'] ?? null) {
            $base->whereHas('preferences', fn (Builder $p) => $p->where('language', $language));
        }

        return $base;
    }

    public function toResult(Model $model): SearchResult
    {
        /** @var User $model */
        $profile = $model->profile;
        $location = collect([$profile?->city, $profile?->country])->filter()->join(', ');

        return new SearchResult(
            type: $this->key(),
            id: $model->id,
            title: $profile?->display_name ?? $model->name,
            subtitle: $profile?->username ? '@'.$profile->username : null,
            description: $profile?->bio ? Str::limit(strip_tags($profile->bio), 100) : ($location ?: null),
            // Phase 22's public profile page — the search result now links
            // somewhere a viewer can actually land, guest or not, instead of
            // funneling everyone through "start a conversation."
            url: $profile?->username ? route('users.show', $profile->username) : null,
            image: $profile?->profilePhoto ? route('files.show', $profile->profilePhoto) : null,
            category: null,
        );
    }
}
