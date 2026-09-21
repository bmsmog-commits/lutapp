<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'title', 'slug', 'description', 'target_amount',
    'currency', 'status', 'visibility', 'starts_at', 'ends_at',
])]
class GivingCampaign extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'closed', 'cancelled'];

    public const VISIBILITIES = ['public', 'private'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'campaign_id');
    }

    public function acceptsDonations(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return ! ($this->ends_at && $this->ends_at->isPast());
    }

    public function targetAmountDisplay(): ?string
    {
        return $this->target_amount === null ? null : Money::toDecimalString($this->target_amount);
    }

    public function raisedAmountMinorUnits(): int
    {
        return (int) $this->donations()->where('status', 'successful')->sum('amount');
    }

    // Same ceiling rule established in Resource/Job: a campaign can never be
    // more discoverable than its owning organization, regardless of its own
    // visibility field.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where('status', 'published')->whereHas('organization', fn ($q) => $q->where('visibility', 'public'))
            ->where(function (Builder $q) use ($user) {
                $q->where('visibility', 'public');

                if ($user) {
                    $organizationIds = $user->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
                        ->merge($user->ownedOrganizations()->pluck('id'));

                    $q->orWhere(fn ($sub) => $sub->where('visibility', 'private')->whereIn('organization_id', $organizationIds));
                }
            });
    }
}
