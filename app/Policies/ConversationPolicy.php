<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    // Starting a conversation is always allowed in principle — the actual
    // counterpart user is validated separately in the controller (must exist,
    // must not be self).
    public function create(User $user): bool
    {
        return ! $user->isRestricted();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->isParticipant($user, $conversation);
    }

    protected function isParticipant(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()->where('user_id', $user->id)->exists();
    }
}
