<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return app(OrganizationPolicy::class)->view($user, $organization);
    }

    public function view(User $user, Department $department): bool
    {
        return app(OrganizationPolicy::class)->view($user, $department->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $organization);
    }

    public function update(User $user, Department $department): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $department->organization);
    }

    public function delete(User $user, Department $department): bool
    {
        return app(OrganizationPolicy::class)->manageMembers($user, $department->organization);
    }
}
