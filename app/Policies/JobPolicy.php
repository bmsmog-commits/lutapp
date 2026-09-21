<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class JobPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->isRestricted();
    }

    public function view(?User $user, Job $job): bool
    {
        if ($this->isManager($user, $job)) {
            return true;
        }

        if ($job->status !== 'published') {
            return false;
        }

        if ($job->visibility === 'public') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($job->organization_id === null) {
            return $job->user_id === $user->id;
        }

        return $this->isOrganizationMember($user, $job);
    }

    public function update(User $user, Job $job): bool
    {
        return $this->isManager($user, $job);
    }

    public function delete(User $user, Job $job): bool
    {
        return $this->isManager($user, $job);
    }

    public function viewApplications(User $user, Job $job): bool
    {
        return $this->isManager($user, $job);
    }

    // Applying requires more than "isManager is false" — the job must actually
    // be open, and the owner/manager may never apply to their own posting.
    public function apply(User $user, Job $job): bool
    {
        if ($this->isManager($user, $job)) {
            return false;
        }

        return $job->acceptsApplications();
    }

    public function isManager(?User $user, Job $job): bool
    {
        if (! $user) {
            return false;
        }

        if ($job->organization_id === null) {
            return $job->user_id === $user->id;
        }

        $organization = $job->organization;

        if ($organization->owner_id === $user->id) {
            return true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        return $user->hasAnyRole(['Organization Owner', 'Organization Admin']);
    }

    protected function isOrganizationMember(User $user, Job $job): bool
    {
        $organization = $job->organization;

        return $organization->owner_id === $user->id
            || $organization->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
