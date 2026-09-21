<?php

namespace App\Policies;

use App\Models\GivingCampaign;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class GivingCampaignPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    // Only Organization Owner/Admin — a plain member must never be able to
    // create a financial campaign for the organization.
    public function create(User $user, ?Organization $organization = null): bool
    {
        if ($user->isRestricted()) {
            return false;
        }

        return $organization === null || $this->isManager($user, $organization);
    }

    public function view(?User $user, GivingCampaign $campaign): bool
    {
        if ($user && $this->isManager($user, $campaign->organization)) {
            return true;
        }

        // A private organization's campaigns never leak, no matter the
        // campaign's own visibility field — same ceiling rule as Resource/Job.
        if ($campaign->organization->visibility !== 'public') {
            return $user !== null && $this->isOrganizationMember($user, $campaign->organization);
        }

        if ($campaign->status !== 'published') {
            return false;
        }

        if ($campaign->visibility === 'public') {
            return true;
        }

        return $user !== null && $this->isOrganizationMember($user, $campaign->organization);
    }

    public function update(User $user, GivingCampaign $campaign): bool
    {
        return $this->isManager($user, $campaign->organization);
    }

    public function delete(User $user, GivingCampaign $campaign): bool
    {
        return $this->isManager($user, $campaign->organization);
    }

    public function viewGivingHistory(User $user, GivingCampaign $campaign): bool
    {
        return $this->isManager($user, $campaign->organization);
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
