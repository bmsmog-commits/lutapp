<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationEvent;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class OrganizationEventPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        if ($user->isRestricted()) {
            return false;
        }

        return $organization === null || $this->isManager($user, $organization);
    }

    public function view(?User $user, OrganizationEvent $event): bool
    {
        if ($user && $this->isManager($user, $event->organization)) {
            return true;
        }

        // A private organization's events never leak, regardless of the
        // event's own visibility field — same ceiling rule as Resource/Job.
        if ($event->organization->visibility !== 'public') {
            return $user !== null && $this->isOrganizationMember($user, $event->organization);
        }

        if ($event->status !== 'published') {
            return false;
        }

        if ($event->visibility === 'public') {
            return true;
        }

        return $user !== null && $this->isOrganizationMember($user, $event->organization);
    }

    public function update(User $user, OrganizationEvent $event): bool
    {
        return $this->isManager($user, $event->organization);
    }

    public function delete(User $user, OrganizationEvent $event): bool
    {
        return $this->isManager($user, $event->organization);
    }

    public function viewAttendance(User $user, OrganizationEvent $event): bool
    {
        return $this->isManager($user, $event->organization);
    }

    // The online/hybrid meeting link is only ever handed to someone who can
    // already see the event AND (is a manager OR is actually RSVP'd) — being
    // merely allowed to view the listing is not enough to receive it.
    public function canSeeOnlineUrl(?User $user, OrganizationEvent $event): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isManager($user, $event->organization)) {
            return true;
        }

        return $event->rsvps()->where('user_id', $user->id)->where('status', 'attending')->exists();
    }

    public function isManager(User $user, Organization $organization): bool
    {
        if ($organization->owner_id === $user->id) {
            return true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        return $user->hasAnyRole(['Organization Owner', 'Organization Admin']);
    }

    protected function isOrganizationMember(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id
            || $organization->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
