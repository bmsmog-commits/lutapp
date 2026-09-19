<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddOrganizationMemberRequest;
use App\Http\Requests\UpdateOrganizationMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class OrganizationMemberController extends Controller
{
    private const SEARCH_RESULT_LIMIT = 10;

    public function index(Request $request, Organization $organization): View
    {
        $this->authorize('viewAny', [OrganizationMember::class, $organization]);

        $members = $organization->members()
            ->with(['user.profile', 'department'])
            ->orderBy('created_at')
            ->paginate(20);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $members->getCollection()->transform(function (OrganizationMember $member) use ($organization) {
            $member->role_names = $member->user_id === $organization->owner_id
                ? collect(['Organization Owner'])
                : $member->user->getRoleNames();

            return $member;
        });

        return view('organizations.members.index', [
            'organization' => $organization,
            'members' => $members,
            'canManage' => $request->user()->can('manageMembers', $organization),
        ]);
    }

    public function create(Request $request, Organization $organization): View
    {
        $this->authorize('create', [OrganizationMember::class, $organization]);

        $query = trim((string) $request->query('q', ''));
        $results = collect();

        if (mb_strlen($query) >= 2 && mb_strlen($query) <= 100) {
            $existingUserIds = $organization->members()->pluck('user_id');

            $results = User::query()
                ->whereNotIn('id', $existingUserIds)
                ->where('id', '!=', $organization->owner_id)
                ->where(function ($q) use ($query) {
                    $q->where('email', $query)
                        ->orWhere('name', 'like', '%'.$query.'%')
                        ->orWhereHas('profile', function ($profileQuery) use ($query) {
                            $profileQuery->where('username', 'like', '%'.$query.'%')
                                ->orWhere('display_name', 'like', '%'.$query.'%');
                        });
                })
                ->with('profile')
                ->limit(self::SEARCH_RESULT_LIMIT)
                ->get();
        }

        return view('organizations.members.add', [
            'organization' => $organization,
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function store(AddOrganizationMemberRequest $request, Organization $organization): RedirectResponse
    {
        $userId = $request->validated('user_id');

        if ($userId === $organization->owner_id) {
            return back()->withErrors(['user_id' => 'This user already owns the organization.']);
        }

        if ($organization->members()->where('user_id', $userId)->exists()) {
            return back()->withErrors(['user_id' => 'This user is already a member of this organization.']);
        }

        try {
            DB::transaction(function () use ($organization, $userId) {
                $member = $organization->members()->create([
                    'user_id' => $userId,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                // New members always start as a plain Member — this endpoint has no
                // path to Admin or Owner, regardless of what the client submits.
                app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
                $member->user->assignRole('Member');
            });
        } catch (QueryException $e) {
            // The unique (organization_id, user_id) constraint is the final guard
            // against a race condition slipping past the existence check above.
            return back()->withErrors(['user_id' => 'This user is already a member of this organization.']);
        }

        return redirect()->route('organizations.members.index', $organization)->with('status', 'Member added.');
    }

    public function update(UpdateOrganizationMemberRequest $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $member);

        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $member->update($data);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $member->user->syncRoles([$role]);

        return redirect()->route('organizations.members.index', $organization)->with('status', 'Member updated.');
    }

    public function destroy(Request $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $member);
        $this->authorize('delete', $member);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $member->user->syncRoles([]);
        $member->delete();

        return redirect()->route('organizations.members.index', $organization)->with('status', 'Member removed.');
    }

    private function assertBelongsToOrganization(Organization $organization, OrganizationMember $member): void
    {
        abort_unless($member->organization_id === $organization->id, 404);
    }
}
