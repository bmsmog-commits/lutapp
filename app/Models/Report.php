<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reporter_id', 'reportable_type', 'reportable_id', 'reason', 'description',
    'status', 'assigned_to', 'resolution', 'resolved_at',
])]
class Report extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'under_review', 'resolved', 'dismissed'];

    public const OPEN_STATUSES = ['pending', 'under_review'];

    public const REASONS = [
        'spam', 'harassment', 'scam_fraud', 'impersonation', 'inappropriate_content',
        'copyright', 'hate_abusive', 'unsafe_dangerous', 'other',
    ];

    // Same (type, id) discriminator convention as Notification::RELATED_*.
    public const TARGET_USER = 'user';

    public const TARGET_JOB = 'job';

    public const TARGET_RESOURCE = 'resource';

    public const TARGET_AUDIO = 'audio';

    public const TARGET_ORGANIZATION_EVENT = 'organization_event';

    public const TARGET_ORGANIZATION = 'organization';

    public const TARGETS = [
        self::TARGET_USER => User::class,
        self::TARGET_JOB => Job::class,
        self::TARGET_RESOURCE => Resource::class,
        self::TARGET_AUDIO => AudioResource::class,
        self::TARGET_ORGANIZATION_EVENT => OrganizationEvent::class,
        self::TARGET_ORGANIZATION => Organization::class,
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ModerationNote::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Resolves the reported record itself, or null if it no longer exists
     * (a moderator's view must render safely even for a since-deleted target).
     */
    public function target(): ?Model
    {
        $class = self::TARGETS[$this->reportable_type] ?? null;

        return $class ? $class::find($this->reportable_id) : null;
    }

    public function targetLabel(): string
    {
        $target = $this->target();

        if (! $target) {
            return ucfirst(str_replace('_', ' ', $this->reportable_type)).' #'.$this->reportable_id.' (deleted)';
        }

        return match ($this->reportable_type) {
            self::TARGET_USER => $target->profile?->display_name ?? $target->name,
            self::TARGET_ORGANIZATION => $target->name,
            default => $target->title ?? (string) $target->getKey(),
        };
    }
}
