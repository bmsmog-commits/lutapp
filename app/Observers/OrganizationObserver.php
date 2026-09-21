<?php

namespace App\Observers;

use App\Models\Organization;
use App\Services\Media\MediaStorageService;
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

    // Phase 10: closes the visibility-drift gap identified in Phase 9. Placed
    // here (not the controller or a manual service call) because it is an
    // invariant of the Organization model's own lifecycle — any code path that
    // flips an organization private must have its logo follow, not just the
    // one form in OrganizationController. Deliberately one-directional: going
    // private force-privatizes the logo; going public never auto-publishes a
    // logo, matching Phase 10 Rule 4 (never silently publish private media).
    public function updated(Organization $organization): void
    {
        if (! $organization->wasChanged('visibility') || $organization->visibility !== 'private') {
            return;
        }

        $logo = $organization->logo;

        if ($logo && $logo->visibility !== 'private') {
            app(MediaStorageService::class)->changeVisibility($logo, 'private');
        }

        // Phase 11: the same drift risk applies to organization-owned resources
        // (book covers/files) — each resource's own effectiveMediaVisibility()
        // already accounts for the organization no longer being public, so this
        // just applies that computed ceiling to whichever ones still need it.
        foreach ($organization->resources as $resource) {
            $resource->syncMediaVisibility();
        }

        // Phase 13: same drift risk for organization-owned job attachments.
        foreach ($organization->jobs as $job) {
            $job->syncMediaVisibility();
        }

        // Phase 16: same drift risk for organization-owned event covers.
        foreach ($organization->events as $event) {
            $event->syncMediaVisibility();
        }

        // Phase 17: same drift risk for organization-owned audio/collection
        // covers — going private force-privatizes them, going public never
        // silently republishes previously private audio.
        foreach ($organization->audioResources as $audio) {
            $audio->syncMediaVisibility();
        }

        foreach ($organization->audioCollections as $collection) {
            if ($collection->cover && $collection->cover->visibility !== 'private') {
                app(MediaStorageService::class)->changeVisibility($collection->cover, 'private');
            }
        }
    }
}
