<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Membership rows cover regular members; an owner may not have a
        // membership row of their own, so owned organizations are unioned in.
        $memberOrganizations = $user->organizations()->wherePivot('status', 'active')->get();
        $ownedOrganizations = $user->ownedOrganizations()->get();

        $organizations = $memberOrganizations->merge($ownedOrganizations)->unique('id')->sortBy('name');

        return view('organizations.index', ['organizations' => $organizations]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Organization::class);

        return view('organizations.create', ['types' => Organization::TYPES]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = Organization::create($request->validatedForCreation());

        return redirect()->route('organizations.show', $organization)->with('status', 'Organization created.');
    }

    public function show(Request $request, Organization $organization): View
    {
        $this->authorize('view', $organization);

        $role = null;
        if ($request->user()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $role = $organization->owner_id === $request->user()->id
                ? 'Organization Owner'
                : $request->user()->getRoleNames()->first();
        }

        return view('organizations.show', [
            'organization' => $organization,
            'memberCount' => $organization->members()->where('status', 'active')->count(),
            'departmentCount' => $organization->departments()->count(),
            'currentUserRole' => $role,
            'canManage' => $request->user()?->can('manageMembers', $organization) ?? false,
        ]);
    }

    public function edit(Request $request, Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', ['organization' => $organization, 'types' => Organization::TYPES]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $data = $request->validated();
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('organization-logos', 'public');
        }

        $organization->update($data);

        return redirect()->route('organizations.show', $organization)->with('status', 'Organization updated.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('organizations.index')->with('status', 'Organization deleted.');
    }
}
