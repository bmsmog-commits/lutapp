<?php

namespace App\Http\Controllers;

use App\Models\AudioResource;
use App\Models\Job;
use App\Models\Resource as LibraryResource;
use App\Models\UserProfile;
use App\Services\PreferenceService;
use App\Services\UserConnectionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserProfileController extends Controller
{
    private const RELATED_LIMIT = 5;

    public function show(Request $request, string $username, PreferenceService $preferences, UserConnectionService $connections): View
    {
        // Looked up by username directly (not implicit route-model-binding on
        // User, whose route key is id) — a UserProfile row is the only way a
        // username resolves to a user at all, matching the existing
        // architecture (Phase 9) rather than introducing a second identity
        // lookup.
        $profile = UserProfile::where('username', $username)->with(['user', 'profilePhoto'])->first();

        // Same response for "no such username" and "exists but private" —
        // deliberately not distinguishing the two, so a private profile's
        // mere existence isn't confirmed by a different status code.
        abort_unless($profile && $preferences->canViewPublicProfile($profile->user, $request->user()), 404);

        $profileUser = $profile->user;

        // Only organizations that are BOTH public and where this membership
        // is active — a private organization's roster, or a pending/removed
        // membership, must never surface here.
        $organizationIds = $profileUser->organizations()->wherePivot('status', 'active')->pluck('organizations.id')
            ->merge($profileUser->ownedOrganizations()->pluck('id'));

        $organizations = \App\Models\Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('visibility', 'public')
            ->with('logo')
            ->orderBy('name')
            ->get();

        return view('users.show', [
            'profile' => $profile,
            'profileUser' => $profileUser,
            'organizations' => $organizations,
            'jobs' => Job::query()->visibleTo($request->user())
                ->where('user_id', $profileUser->id)
                ->latest()->limit(self::RELATED_LIMIT)->get(),
            'resources' => LibraryResource::query()->visibleTo($request->user())
                ->where('user_id', $profileUser->id)
                ->latest()->limit(self::RELATED_LIMIT)->get(),
            'audio' => AudioResource::query()->visibleTo($request->user())
                ->where('user_id', $profileUser->id)
                ->latest()->limit(self::RELATED_LIMIT)->get(),
            'canMessage' => $request->user() && ! $request->user()->is($profileUser)
                && ! $connections->isBlockedEitherWay($request->user(), $profileUser),
            'followersCount' => $profileUser->followers()->count(),
            'followingCount' => $profileUser->following()->count(),
            'isSelf' => $request->user()?->is($profileUser) ?? false,
            'isFollowing' => $request->user() ? $connections->isFollowing($request->user(), $profileUser) : false,
            'isBlocked' => $request->user() ? $connections->isBlocked($request->user(), $profileUser) : false,
            'canFollow' => $request->user() ? $connections->canFollow($request->user(), $profileUser) : false,
        ]);
    }
}
