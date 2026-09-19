<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        if ($organization->visibility === 'public') {
            return true;
        }

        return $this->isOwner($user, $organization) || $this->isActiveMember($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization) || $this->hasOrganizationRole($user, $organization, ['Organization Owner', 'Organization Admin']);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization) || $this->hasOrganizationRole($user, $organization, ['Organization Owner', 'Organization Admin']);
    }

    protected function isOwner(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id;
    }

    protected function isActiveMember(User $user, Organization $organization): bool
    {
        return $organization->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    protected function hasOrganizationRole(User $user, Organization $organization, array $roles): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        return $user->hasAnyRole($roles);
    }
}
