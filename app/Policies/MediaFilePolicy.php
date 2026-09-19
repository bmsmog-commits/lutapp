<?php

namespace App\Policies;

use App\Models\MediaFile;
use App\Models\Organization;
use App\Models\User;

class MediaFilePolicy
{
    public function view(User $user, MediaFile $media): bool
    {
        if ($media->isPublic()) {
            return true;
        }

        if ($media->isPersonal()) {
            return $media->user_id === $user->id;
        }

        // Deliberately NOT OrganizationPolicy::view — that governs the organization's
        // own public profile page, which may be visible to anyone regardless of
        // membership. A private file needs actual membership, independent of whether
        // the organization itself happens to have a public profile.
        return $this->isOrganizationMember($user, $media->organization);
    }

    public function download(User $user, MediaFile $media): bool
    {
        return $this->view($user, $media);
    }

    public function delete(User $user, MediaFile $media): bool
    {
        if ($media->isPersonal()) {
            return $media->user_id === $user->id;
        }

        return app(OrganizationPolicy::class)->manageMembers($user, $media->organization);
    }

    public function update(User $user, MediaFile $media): bool
    {
        return $this->delete($user, $media);
    }

    protected function isOrganizationMember(User $user, Organization $organization): bool
    {
        return $organization->owner_id === $user->id
            || $organization->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
