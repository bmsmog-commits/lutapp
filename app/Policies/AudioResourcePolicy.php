<?php

namespace App\Policies;

use App\Models\AudioResource;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class AudioResourcePolicy
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

        return $organization === null || $this->isOrganizationManager($user, $organization);
    }

    public function view(?User $user, AudioResource $audio): bool
    {
        if ($this->isManager($user, $audio)) {
            return true;
        }

        if ($audio->status !== 'published') {
            return false;
        }

        if ($audio->organization_id === null) {
            return $audio->visibility === 'public' || ($user && $audio->user_id === $user->id);
        }

        if ($audio->organization->visibility !== 'public') {
            return $user !== null && $this->isOrganizationMember($user, $audio->organization);
        }

        if ($audio->visibility === 'public') {
            return true;
        }

        return $user !== null && $this->isOrganizationMember($user, $audio->organization);
    }

    public function update(User $user, AudioResource $audio): bool
    {
        return $this->isManager($user, $audio);
    }

    public function delete(User $user, AudioResource $audio): bool
    {
        return $this->isManager($user, $audio);
    }

    protected function isManager(?User $user, AudioResource $audio): bool
    {
        if (! $user) {
            return false;
        }

        if ($audio->organization_id === null) {
            return $audio->user_id === $user->id;
        }

        return $this->isOrganizationManager($user, $audio->organization);
    }

    public function isOrganizationManager(User $user, Organization $organization): bool
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
