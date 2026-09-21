<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class ResourcePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->isRestricted();
    }

    public function view(?User $user, Resource $resource): bool
    {
        if ($this->isManager($user, $resource)) {
            return true;
        }

        if ($resource->status !== 'published') {
            return false;
        }

        if ($resource->visibility === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($resource->organization_id === null) {
            return $resource->user_id === $user->id;
        }

        return $this->isOrganizationMember($user, $resource);
    }

    public function update(User $user, Resource $resource): bool
    {
        return $this->isManager($user, $resource);
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $this->isManager($user, $resource);
    }

    protected function isManager(?User $user, Resource $resource): bool
    {
        if (! $user) {
            return false;
        }

        if ($resource->organization_id === null) {
            return $resource->user_id === $user->id;
        }

        $organization = $resource->organization;

        if ($organization->owner_id === $user->id) {
            return true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        return $user->hasAnyRole(['Organization Owner', 'Organization Admin']);
    }

    protected function isOrganizationMember(User $user, Resource $resource): bool
    {
        $organization = $resource->organization;

        return $organization->owner_id === $user->id
            || $organization->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
