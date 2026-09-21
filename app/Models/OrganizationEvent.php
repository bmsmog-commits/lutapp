<?php

namespace App\Models;

use App\Services\Media\MediaStorageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'organization_id', 'creator_id', 'title', 'slug', 'description', 'category',
    'status', 'visibility', 'starts_at', 'ends_at', 'timezone', 'location_mode',
    'country', 'state', 'city', 'address', 'latitude', 'longitude', 'online_url',
    'capacity', 'cover_media_id',
])]
class OrganizationEvent extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'cancelled', 'completed'];

    public const VISIBILITIES = ['public', 'private'];

    public const LOCATION_MODES = ['physical', 'online', 'hybrid'];

    public const CATEGORIES = [
        'Church Service', 'Bible Study', 'Conference', 'Seminar', 'Workshop',
        'Meeting', 'Crusade', 'Prayer Meeting', 'Community Program', 'Youth Program', 'Other',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class, 'event_id');
    }

    public function attendingCount(): int
    {
        return $this->rsvps()->where('status', 'attending')->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->attendingCount() >= $this->capacity;
    }

    public function acceptsRsvps(): bool
    {
        return $this->status === 'published';
    }

    // Never returned by a public-facing path — a private/hybrid event's
    // meeting link is not "location info," it's an access credential.
    public function publicOnlineUrl(?User $viewer): ?string
    {
        if (in_array($this->location_mode, ['online', 'hybrid'], true) && app(\App\Policies\OrganizationEventPolicy::class)->canSeeOnlineUrl($viewer, $this)) {
            return $this->online_url;
        }

        return null;
    }

    public function effectiveMediaVisibility(): string
    {
        if ($this->status !== 'published') {
            return 'private';
        }

        if ($this->organization->visibility !== 'public') {
            return 'private';
        }

        return $this->visibility;
    }

    public function syncMediaVisibility(): void
    {
        $target = $this->effectiveMediaVisibility();

        if ($this->cover && $this->cover->visibility !== $target) {
            app(MediaStorageService::class)->changeVisibility($this->cover, $target);
        }
    }

    // Same ceiling rule as Resource/Job/GivingCampaign: never more discoverable
    // than the owning organization, regardless of the event's own visibility.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where('status', 'published')
            ->whereHas('organization', fn ($q) => $q->where('visibility', 'public'))
            ->where(function (Builder $q) use ($user) {
                $q->where('visibility', 'public');

                if ($user) {
                    $organizationIds = $user->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
                        ->merge($user->ownedOrganizations()->pluck('id'));

                    $q->orWhere(fn ($sub) => $sub->where('visibility', 'private')->whereIn('organization_id', $organizationIds));
                }
            });
    }

    /**
     * Atomically RSVPs the given user, respecting capacity under concurrent
     * requests. Locks the event row first so two simultaneous requests for
     * the last available spot serialize instead of racing past the count
     * check together.
     */
    public function rsvp(User $user): EventRsvp
    {
        return DB::transaction(function () use ($user) {
            $locked = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $existing = EventRsvp::where('event_id', $locked->id)->where('user_id', $user->id)->first();

            if ($existing && $existing->status === 'attending') {
                return $existing;
            }

            if ($locked->isFull()) {
                throw new EventFullException('This event has reached capacity.');
            }

            if ($existing) {
                $existing->update(['status' => 'attending']);

                return $existing->refresh();
            }

            return EventRsvp::create(['event_id' => $locked->id, 'user_id' => $user->id, 'status' => 'attending']);
        });
    }
}
