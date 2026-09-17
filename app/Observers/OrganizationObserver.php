<?php

namespace App\Observers;

use App\Models\Organization;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class OrganizationObserver
{
    // Roles are scoped per-organization (Spatie teams), so each new organization
    // needs its own baseline role set before members can be promoted within it.
    public const BASE_ROLES = ['Organization Owner', 'Organization Admin', 'Member'];

    public function created(Organization $organization): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        foreach (self::BASE_ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
                'organization_id' => $organization->id,
            ]);
        }

        $organization->owner->assignRole('Organization Owner');
    }
}
