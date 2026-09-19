<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class OrganizationMemberPolicy
{
    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        return $organization ? app(OrganizationPolicy::class)->view($user, $organization) : true;
    }

    // Governs both the member-search page and the direct-add submission — same
    // authorization as any other membership management action (owner/admin only).
    public function create(User $user, Organization $organization): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $organization);
    }

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

    // The sole owner's membership row can never be removed through this policy —
    // ownership transfer is a deliberately deferred feature (see Phase 6 report),
    // so there is no safe path that would leave an organization without an owner.
    public function delete(User $user, OrganizationMember $member): bool
    {
        if ($member->user_id === $member->organization->owner_id) {
            return false;
        }

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
