<?php

namespace App\Models;

use App\Services\Media\MediaStorageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id', 'organization_id', 'type', 'title', 'slug', 'description',
    'author', 'publisher', 'published_on', 'language_id', 'category',
    'status', 'visibility', 'cover_media_id', 'file_media_id',
])]
class Resource extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'archived'];

    public const VISIBILITIES = ['public', 'private'];

    public const CATEGORIES = [
        'Christian Books', 'Bible Study', 'Devotional', 'Sermon',
        'Teaching', 'Leadership', 'Personal Development', 'Other',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_media_id');
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_resources')->withTimestamps();
    }

    // Only published, appropriately-visible resources belong in the public
    // library listing — drafts/archived resources are reached only through the
    // owner/organization-manager's own view, never this scope.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where('status', 'published')->where(function (Builder $q) use ($user) {
            $q->where('visibility', 'public');

            if ($user) {
                $organizationIds = $user->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
                    ->merge($user->ownedOrganizations()->pluck('id'));

                $q->orWhere(fn ($sub) => $sub->where('visibility', 'private')->where('user_id', $user->id))
                    ->orWhere(fn ($sub) => $sub->where('visibility', 'private')->whereIn('organization_id', $organizationIds));
            }
        })
            // Phase 24: see Job::scopeVisibleTo() for the identical rule.
            ->where(function (Builder $q) {
                $q->whereNotNull('organization_id')
                    ->orWhereHas('user', fn (Builder $u) => $u->whereNotIn('account_status', ['suspended', 'deactivated']));
            });
    }

    // The strictest of (status, organization visibility, resource visibility)
    // always wins — a draft, or a resource owned by a private organization, can
    // never have publicly-servable media regardless of its own visibility field.
    public function effectiveMediaVisibility(): string
    {
        if ($this->status !== 'published') {
            return 'private';
        }

        if ($this->organization_id && $this->organization->visibility !== 'public') {
            return 'private';
        }

        return $this->visibility;
    }

    public function syncMediaVisibility(): void
    {
        $target = $this->effectiveMediaVisibility();
        $storage = app(MediaStorageService::class);

        foreach (['cover', 'file'] as $relation) {
            $media = $this->{$relation};

            if ($media && $media->visibility !== $target) {
                $storage->changeVisibility($media, $target);
            }
        }
    }
}
