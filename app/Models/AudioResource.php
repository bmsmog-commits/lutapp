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
    'user_id', 'organization_id', 'title', 'slug', 'description',
    'creator_name', 'creator_user_id', 'category', 'language_id',
    'duration_seconds', 'released_on', 'status', 'visibility',
    'audio_media_id', 'cover_media_id',
])]
class AudioResource extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'archived'];

    public const VISIBILITIES = ['public', 'private'];

    public const CATEGORIES = [
        'Worship', 'Gospel', 'Hymn', 'Sermon', 'Teaching', 'Podcast',
        'Spoken Word', 'Instrumental', 'Other',
    ];

    protected function casts(): array
    {
        return [
            'released_on' => 'date',
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

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function audioMedia(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_media_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(AudioCollection::class, 'audio_collection_items', 'audio_resource_id', 'collection_id')
            ->withPivot('position')->withTimestamps();
    }

    public function creatorDisplayName(): string
    {
        return $this->creatorUser?->name ?? $this->creator_name ?? 'Unknown';
    }

    // Same ceiling rule as Resource/Job/GivingCampaign/OrganizationEvent: a
    // personal audio resource has no organization to cap it, but an
    // organization-owned one can never be more discoverable than its org.
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

        foreach (['audioMedia', 'cover'] as $relation) {
            $media = $this->{$relation};

            if ($media && $media->visibility !== $target) {
                $storage->changeVisibility($media, $target);
            }
        }
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where('status', 'published')->where(function (Builder $q) use ($user) {
            // Public branch: public visibility, and (personal, or belonging to
            // a public organization) — a private organization's audio never
            // qualifies here no matter its own visibility field.
            $q->where(function (Builder $pub) {
                $pub->where('visibility', 'public')
                    ->where(function (Builder $orgCheck) {
                        $orgCheck->whereNull('organization_id')
                            ->orWhereHas('organization', fn (Builder $o) => $o->where('visibility', 'public'));
                    });
            });

            if ($user) {
                $organizationIds = $user->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
                    ->merge($user->ownedOrganizations()->pluck('id'));

                $q->orWhere(fn (Builder $sub) => $sub->where('user_id', $user->id))
                    ->orWhere(fn (Builder $sub) => $sub->whereIn('organization_id', $organizationIds));
            }
        })
            // Phase 24: see Job::scopeVisibleTo() for the identical rule.
            ->where(function (Builder $q) {
                $q->whereNotNull('organization_id')
                    ->orWhereHas('user', fn (Builder $u) => $u->whereNotIn('account_status', ['suspended', 'deactivated']));
            });
    }
}
