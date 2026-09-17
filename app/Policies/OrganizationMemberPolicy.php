<?php

namespace App\Policies;

use App\Models\OrganizationMember;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class OrganizationMemberPolicy
{
    public function view(User $user, OrganizationMember $member): bool
    {
        return app(OrganizationPolicy::class)->view($user, $member->organization);
    }

    // Only an owner/admin of the member's organization may change role, department or
    // status — a member can never grant themselves elevated access this way.
    public function update(User $user, OrganizationMember $member): bool
    {
        return $this->isOrganizationManager($user, $member);
    }

    public function delete(User $user, OrganizationMember $member): bool
    {
        return $this->isOrganizationManager($user, $member);
    }

    protected function isOrganizationManager(User $user, OrganizationMember $member): bool
    {
        $organization = $member->organization;

        if ($organization->owner_id === $user->id) {
            return true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        return $user->hasAnyRole(['Organization Owner', 'Organization Admin']);
    }
}
