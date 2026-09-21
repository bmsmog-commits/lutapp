<?php

namespace App\Policies;

use App\Models\EventRsvp;
use App\Models\User;

class EventRsvpPolicy
{
    public function view(User $user, EventRsvp $rsvp): bool
    {
        return $rsvp->user_id === $user->id
            || app(OrganizationEventPolicy::class)->isManager($user, $rsvp->event->organization);
    }

    // A user may only ever cancel their own RSVP — not even an organization
    // manager may cancel it on their behalf, keeping this identical in shape
    // to how a user manages their own saved resources/message read state.
    public function cancel(User $user, EventRsvp $rsvp): bool
    {
        return $rsvp->user_id === $user->id;
    }
}
