<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;

#[Fillable([
    'user_id', 'actor_id', 'organization_id', 'type', 'title', 'body', 'related_type', 'related_id', 'read_at',
])]
class Notification extends Model
{
    use HasFactory;

    protected $table = 'app_notifications';

    public const RELATED_CONVERSATION = 'conversation';

    public const RELATED_JOB = 'job';

    public const RELATED_JOB_APPLICATION = 'job_application';

    public const RELATED_ORGANIZATION_EVENT = 'organization_event';

    public const RELATED_ORGANIZATION = 'organization';

    public const RELATED_DONATION = 'donation';

    public const RELATED_USER = 'user';

    public const RELATED_REPORT = 'report';

    // Controlled vocabulary => notification-preference category. Anything not
    // listed here falls back to the 'system' category (see categoryFor()).
    // 'connections' (Phase 23) and 'moderation' (Phase 24) are newer
    // categories — they surface automatically in Settings' notification-
    // preferences UI since that list is derived from this array, not
    // hand-maintained separately.
    public const TYPES = [
        'message.new' => 'messages',
        'job.application.created' => 'jobs',
        'job.application.accepted' => 'jobs',
        'job.application.rejected' => 'jobs',
        'job.application.withdrawn' => 'jobs',
        'event.rsvp.new' => 'events',
        'event.cancelled' => 'events',
        'organization.member.added' => 'organization',
        'organization.member.removed' => 'organization',
        'giving.donation.successful' => 'giving',
        'user.followed' => 'connections',
        'report.submitted' => 'moderation',
        'report.resolved' => 'moderation',
        'moderation.warned' => 'moderation',
        'moderation.account_status_changed' => 'moderation',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public static function categoryFor(string $type): string
    {
        return self::TYPES[$type] ?? 'system';
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Builds a safe internal URL to the related resource, or null when there
     * is none, it no longer exists, or the given viewer is no longer
     * authorized to see it — a notification must always render safely rather
     * than 404 or leak access the viewer has since lost (e.g. a removed
     * organization member).
     */
    public function relatedUrl(?User $viewer): ?string
    {
        if (! $this->related_type || ! $this->related_id || ! $viewer) {
            return null;
        }

        try {
            return match ($this->related_type) {
                self::RELATED_CONVERSATION => $this->urlIfAuthorized(
                    $viewer, Conversation::find($this->related_id), 'view', fn ($m) => route('messages.show', $m)
                ),
                self::RELATED_JOB => $this->urlIfAuthorized(
                    $viewer, Job::find($this->related_id), 'view', fn ($m) => route('jobs.show', $m)
                ),
                self::RELATED_JOB_APPLICATION => $this->jobApplicationUrl($viewer),
                self::RELATED_ORGANIZATION_EVENT => $this->urlIfAuthorized(
                    $viewer, OrganizationEvent::find($this->related_id), 'view', fn ($m) => route('org-events.show', $m)
                ),
                self::RELATED_ORGANIZATION => $this->urlIfAuthorized(
                    $viewer, Organization::find($this->related_id), 'view', fn ($m) => route('organizations.show', $m)
                ),
                // No single-donation detail route exists (Phase 15) — the
                // donor's own giving history is the closest safe destination,
                // and it's already scoped to the viewer by definition.
                self::RELATED_DONATION => route('giving.mine'),
                self::RELATED_USER => $this->userProfileUrl(),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function userProfileUrl(): ?string
    {
        $user = User::with('profile')->find($this->related_id);
        $username = $user?->profile?->username;

        if (! $username) {
            return null;
        }

        // No User policy exists for this — the same discoverable check the
        // profile route itself enforces (via PreferenceService), applied
        // directly, since a follower could have gone private since the
        // notification was created.
        if (! app(\App\Services\PreferenceService::class)->isDiscoverable($user)) {
            return null;
        }

        return route('users.show', $username);
    }

    private function jobApplicationUrl(User $viewer): ?string
    {
        $application = JobApplication::find($this->related_id);

        if (! $application) {
            return null;
        }

        return $this->urlIfAuthorized($viewer, $application, 'view', fn ($m) => route('jobs.applications.show', [$m->job, $m]));
    }

    private function urlIfAuthorized(User $viewer, mixed $model, string $ability, \Closure $urlBuilder): ?string
    {
        if (! $model) {
            return null;
        }

        if (! Gate::forUser($viewer)->allows($ability, $model)) {
            return null;
        }

        return $urlBuilder($model);
    }
}
