<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Services\PreferenceService;
use App\Services\UserConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserConnectionController extends Controller
{
    private const LIST_PER_PAGE = 30;

    public function follow(Request $request, string $username, UserConnectionService $connections, PreferenceService $preferences): RedirectResponse
    {
        // Same 404 (not 403/redirect) a private profile's own page returns —
        // there is nothing here to follow if the profile itself can't be
        // reached, and the response must not distinguish "doesn't exist"
        // from "exists but private."
        $target = $this->resolveViewableTargetUser($username, $preferences, $request->user());

        try {
            $connections->follow($request->user(), $target);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['follow' => $e->getMessage()]);
        }

        return redirect()->route('users.show', $username);
    }

    public function unfollow(Request $request, string $username, UserConnectionService $connections): RedirectResponse
    {
        $target = $this->resolveTargetUser($username);

        // A user only ever removes their OWN outgoing relationship — there is
        // no relationship ID here to manipulate, and the delete is scoped to
        // request()->user() regardless of what $username resolves to.
        $connections->unfollow($request->user(), $target);

        return redirect()->route('users.show', $username);
    }

    public function block(Request $request, string $username, UserConnectionService $connections, PreferenceService $preferences): RedirectResponse
    {
        $target = $this->resolveViewableTargetUser($username, $preferences, $request->user());

        try {
            $connections->block($request->user(), $target);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['block' => $e->getMessage()]);
        }

        return redirect()->route('users.show', $username);
    }

    public function unblock(Request $request, string $username, UserConnectionService $connections): RedirectResponse
    {
        $target = $this->resolveTargetUser($username);

        $connections->unblock($request->user(), $target);

        return redirect()->route('users.show', $username);
    }

    public function followers(Request $request, string $username, PreferenceService $preferences): View
    {
        $profile = $this->resolveProfile($username);
        abort_unless($preferences->canViewPublicProfile($profile->user, $request->user()), 404);

        return view('users.followers', [
            'profile' => $profile,
            'followers' => $profile->user->followers()->with('profile.profilePhoto')->latest('user_connections.created_at')->paginate(self::LIST_PER_PAGE),
        ]);
    }

    public function following(Request $request, string $username, PreferenceService $preferences): View
    {
        $profile = $this->resolveProfile($username);
        abort_unless($preferences->canViewPublicProfile($profile->user, $request->user()), 404);

        return view('users.following', [
            'profile' => $profile,
            'following' => $profile->user->following()->with('profile.profilePhoto')->latest('user_connections.created_at')->paginate(self::LIST_PER_PAGE),
        ]);
    }

    private function resolveProfile(string $username): UserProfile
    {
        $profile = UserProfile::where('username', $username)->with('user')->first();

        abort_unless($profile, 404);

        return $profile;
    }

    private function resolveTargetUser(string $username): \App\Models\User
    {
        return $this->resolveProfile($username)->user;
    }

    // Used only where a NEW interaction is being initiated (follow/block) —
    // unfollow/unblock deliberately use resolveTargetUser() without this
    // check, so a user can still remove a relationship with an account that
    // has since gone private.
    private function resolveViewableTargetUser(string $username, PreferenceService $preferences, ?\App\Models\User $viewer): \App\Models\User
    {
        $target = $this->resolveTargetUser($username);

        abort_unless($preferences->canViewPublicProfile($target, $viewer), 404);

        return $target;
    }
}
