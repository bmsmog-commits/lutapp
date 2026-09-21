<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserConnection;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single place that creates/removes follow and block relationships —
 * UserConnectionController stays a thin pass-through. Centralizing this here
 * (rather than duplicating the "can these two users interact?" check across
 * MessageController/ConversationController) keeps the block rule from
 * drifting between the follow feature and messaging.
 */
class UserConnectionService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PreferenceService $preferences,
    ) {
    }

    public function isFollowing(User $follower, User $target): bool
    {
        return UserConnection::where('follower_id', $follower->id)->where('following_id', $target->id)->exists();
    }

    public function isBlocked(User $blocker, User $target): bool
    {
        return UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->exists();
    }

    // Blocking is symmetric for interaction purposes — it doesn't matter
    // which of the two placed the block, neither may follow or message the
    // other while it stands.
    public function isBlockedEitherWay(User $a, User $b): bool
    {
        return $this->isBlocked($a, $b) || $this->isBlocked($b, $a);
    }

    /**
     * The one shared gate for "may $actor start a new interaction (message,
     * follow) with $target right now?" — used by MessageController,
     * ConversationController, and UserConnectionService::canFollow, so the
     * block/moderation rules can never drift between features.
     *
     * A 'restricted' actor may not initiate new outbound interaction, but a
     * restricted user can still be reached by others — restriction limits
     * what the restricted account can DO, not their own visibility.
     */
    public function canInteract(User $actor, User $target): bool
    {
        if ($this->isBlockedEitherWay($actor, $target)) {
            return false;
        }

        if ($actor->account_status === 'restricted') {
            return false;
        }

        return ! $target->isAccountHidden();
    }

    public function canFollow(User $follower, User $target): bool
    {
        if ($follower->is($target)) {
            return false;
        }

        if (! $this->canInteract($follower, $target)) {
            return false;
        }

        // A non-discoverable (or, Phase 24, suspended/deactivated) profile is
        // unreachable at all except to its owner — following it must be
        // equally impossible, not just hidden behind an unreachable UI button.
        return $this->preferences->canViewPublicProfile($target, $follower);
    }

    /**
     * Idempotent: following an already-followed user is a safe no-op and
     * never creates a duplicate row or a duplicate notification.
     */
    public function follow(User $follower, User $target): UserConnection
    {
        if (! $this->canFollow($follower, $target)) {
            throw new \RuntimeException('This user cannot be followed.');
        }

        $connection = UserConnection::firstOrCreate([
            'follower_id' => $follower->id,
            'following_id' => $target->id,
        ]);

        if ($connection->wasRecentlyCreated) {
            $followerName = $follower->profile?->display_name ?? $follower->name;

            $this->notifications->notify(
                recipient: $target,
                type: 'user.followed',
                title: $followerName.' started following you',
                actor: $follower,
                relatedType: Notification::RELATED_USER,
                relatedId: $follower->id,
            );
        }

        return $connection;
    }

    public function unfollow(User $follower, User $target): void
    {
        UserConnection::where('follower_id', $follower->id)->where('following_id', $target->id)->delete();
    }

    /**
     * Blocking also tears down any existing follow relationship in either
     * direction — a block must not leave a stale "Following"/"Follower"
     * state standing on either side.
     */
    public function block(User $blocker, User $target): UserBlock
    {
        if ($blocker->is($target)) {
            throw new \RuntimeException('You cannot block yourself.');
        }

        $block = UserBlock::firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $target->id,
        ]);

        UserConnection::where(function (Builder $q) use ($blocker, $target) {
            $q->where('follower_id', $blocker->id)->where('following_id', $target->id);
        })->orWhere(function (Builder $q) use ($blocker, $target) {
            $q->where('follower_id', $target->id)->where('following_id', $blocker->id);
        })->delete();

        return $block;
    }

    public function unblock(User $blocker, User $target): void
    {
        UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->delete();
    }
}
