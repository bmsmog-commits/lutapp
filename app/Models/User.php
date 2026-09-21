<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function todoItems(): HasMany
    {
        return $this->hasMany(TodoItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function hymns(): HasMany
    {
        return $this->hasMany(Hymn::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function securityQuestions(): HasMany
    {
        return $this->hasMany(SecurityQuestion::class);
    }

    public function bibleBookmarks(): HasMany
    {
        return $this->hasMany(BibleBookmark::class);
    }

    public function bibleHighlights(): HasMany
    {
        return $this->hasMany(BibleHighlight::class);
    }

    public function bibleReadingHistory(): HasMany
    {
        return $this->hasMany(BibleReadingHistory::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['department_id', 'job_title', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedResources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function savedResources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'saved_resources')->withTimestamps();
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function ownedJobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'applicant_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function eventRsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function audioResources(): HasMany
    {
        return $this->hasMany(AudioResource::class);
    }

    public function audioCollections(): HasMany
    {
        return $this->hasMany(AudioCollection::class);
    }

    // Deliberately overrides the Notifiable trait's own notifications()
    // relation (which targets Laravel's built-in DatabaseNotification /
    // 'notifications' table — unused anywhere in this app) with this app's
    // own centralized Notification model on a distinctly-named table.
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->unread()->count();
    }

    // Users this account follows.
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_connections', 'follower_id', 'following_id')->withTimestamps();
    }

    // Users who follow this account.
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_connections', 'following_id', 'follower_id')->withTimestamps();
    }

    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')->withTimestamps();
    }

    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_moderator' => 'boolean',
            'account_status_changed_at' => 'datetime',
        ];
    }

    // Deliberately independent of Spatie's org-scoped roles — see the
    // migration comment on is_moderator. An Organization Owner/Admin is NOT
    // automatically a platform moderator.
    public function isModerator(): bool
    {
        return (bool) $this->is_moderator;
    }

    public function isAccountActive(): bool
    {
        return $this->account_status === 'active';
    }

    // 'restricted' keeps the profile/content visible but limits new outbound
    // interaction (see ModerationService) — 'suspended'/'deactivated' hide
    // everything, same as a non-discoverable profile.
    public function isAccountHidden(): bool
    {
        return in_array($this->account_status, ['suspended', 'deactivated'], true);
    }

    // Used by content-creation policies (Job/Resource/AudioResource/
    // OrganizationEvent) to enforce the "restricted" account status's
    // documented behavior: profile/content stays visible, but the user can't
    // create new outbound content. Existing content and edits are untouched.
    public function isRestricted(): bool
    {
        return $this->account_status === 'restricted';
    }

    public function reportsSubmitted(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }
}
