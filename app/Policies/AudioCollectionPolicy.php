<?php

namespace App\Policies;

use App\Models\AudioCollection;
use App\Models\Organization;
use App\Models\User;

class AudioCollectionPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        return $organization === null || app(AudioResourcePolicy::class)->isOrganizationManager($user, $organization);
    }

    public function view(?User $user, AudioCollection $collection): bool
    {
        if ($this->isManager($user, $collection)) {
            return true;
        }

        if ($collection->status !== 'published') {
            return false;
        }

        if ($collection->organization_id === null) {
            return $collection->visibility === 'public';
        }

        if ($collection->organization->visibility !== 'public') {
            return $user !== null && $this->isOrganizationMember($user, $collection->organization);
        }

        return $collection->visibility === 'public' || ($user !== null && $this->isOrganizationMember($user, $collection->organization));
    }

    public function update(User $user, AudioCollection $collection): bool
    {
        return $this->isManager($user, $collection);
    }

    public function delete(User $user, AudioCollection $collection): bool
    {
        return $this->isManager($user, $collection);
    }

    // Adding/removing/reordering items requires managing both the collection
    // AND the audio resource being attached — a manager must not be able to
    // pull another user's/organization's private audio into their collection
    // just by knowing its ID.
    public function manageItems(User $user, AudioCollection $collection): bool
    {
        return $this->isManager($user, $collection);
    }

    protected function isManager(?User $user, AudioCollection $collection): bool
    {
        if (! $user) {
            return false;
        }

        if ($collection->organization_id === null) {
            return $collection->user_id === $user->id;
        }

        return app(AudioResourcePolicy::class)->isOrganizationManager($user, $collection->organization);
    }

    protected function isOrganizationMember(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id
            || $organization->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
