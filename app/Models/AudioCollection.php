<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'organization_id', 'title', 'slug', 'description', 'type', 'status', 'visibility', 'cover_media_id'])]
class AudioCollection extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'archived'];

    public const VISIBILITIES = ['public', 'private'];

    public const TYPES = ['album', 'sermon_series', 'teaching_series', 'podcast_series', 'worship_collection', 'other'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(AudioResource::class, 'audio_collection_items', 'collection_id', 'audio_resource_id')
            ->withPivot('position')->withTimestamps()->orderBy('audio_collection_items.position');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where('status', 'published')->where(function (Builder $q) use ($user) {
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
        });
    }

    public function addItem(AudioResource $resource): void
    {
        $nextPosition = ((int) $this->items()->max('audio_collection_items.position')) + 1;

        $this->items()->syncWithoutDetaching([$resource->id => ['position' => $nextPosition]]);
    }

    public function removeItem(AudioResource $resource): void
    {
        $this->items()->detach($resource->id);
    }

    /**
     * @param  int[]  $orderedResourceIds  every item's audio_resource_id, in the desired order
     */
    public function reorder(array $orderedResourceIds): void
    {
        DB::transaction(function () use ($orderedResourceIds) {
            foreach (array_values($orderedResourceIds) as $index => $resourceId) {
                $this->items()->updateExistingPivot($resourceId, ['position' => $index + 1]);
            }
        });
    }
}
