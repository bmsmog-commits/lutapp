<?php

namespace App\Models;

use App\Services\Media\MediaStorageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'organization_id', 'title', 'slug', 'description', 'category',
    'status', 'visibility', 'work_mode', 'country', 'state', 'city',
    'budget_type', 'budget_min', 'budget_max', 'currency',
    'application_deadline', 'attachment_media_id',
])]
class Job extends Model
{
    use HasFactory;

    protected $table = 'jobs_board';

    public const STATUSES = ['draft', 'published', 'closed', 'cancelled'];

    public const VISIBILITIES = ['public', 'private'];

    public const WORK_MODES = ['remote', 'on_site', 'hybrid'];

    public const BUDGET_TYPES = ['fixed', 'range', 'negotiable'];

    public const CATEGORIES = [
        'Graphic Design', 'Web Development', 'Software Development', 'AI & Automation',
        'Writing', 'Marketing', 'Video & Photography', 'Music', 'Administration',
        'Construction', 'Education', 'Consulting', 'Other',
    ];

    protected function casts(): array
    {
        return [
            'application_deadline' => 'date',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
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

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'attachment_media_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    // The job's own effective owner user for authorization/messaging — the
    // organization's owner stands in for an organization-owned job, since
    // Messaging only supports 1:1 conversations and needs one stable identity.
    public function ownerUser(): ?User
    {
        return $this->organization_id ? $this->organization->owner : $this->user;
    }

    public function isExpired(): bool
    {
        return $this->application_deadline !== null && $this->application_deadline->isPast();
    }

    public function acceptsApplications(): bool
    {
        return $this->status === 'published' && ! $this->isExpired();
    }

    // Mirrors Resource::scopeVisibleTo — only published, appropriately-visible
    // jobs belong in the public discovery listing.
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
            // Phase 24: a personally-owned (not organization-owned) job is
            // excluded from discovery if its owner is suspended/deactivated —
            // organization-owned jobs are unaffected, since an organization's
            // own visibility already governs those independently of any one
            // member's account status.
            ->where(function (Builder $q) {
                $q->whereNotNull('organization_id')
                    ->orWhereHas('user', fn (Builder $u) => $u->whereNotIn('account_status', ['suspended', 'deactivated']));
            });
    }

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

        if ($this->attachment && $this->attachment->visibility !== $target) {
            $storage->changeVisibility($this->attachment, $target);
        }
    }
}
