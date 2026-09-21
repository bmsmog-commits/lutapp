<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $organization->update($request->validated());

        return redirect()->route('organizations.show', $organization)->with('status', 'Organization updated.');
    }

    public function storeLogo(Request $request, Organization $organization, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $organization);

        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('media.max_size_kb.image')],
        ]);

        $oldLogo = $organization->logo;

        // Logo visibility follows the organization's own visibility (Rule 11/19):
        // a public organization's logo is servable to guests viewing its public
        // profile, but a private organization's logo requires the same membership
        // check as any other private organization file.
        $visibility = $organization->visibility === 'public' ? 'public' : 'private';

        try {
            $newLogo = $storage->store($request->file('logo'), ['organization_id' => $organization->id], $visibility);
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['logo' => $e->getMessage()]);
        }

        $organization->update(['logo_media_id' => $newLogo->id]);

        if ($oldLogo) {
            $storage->delete($oldLogo);
        }

        return redirect()->route('organizations.edit', $organization)->with('status', 'Organization logo updated.');
    }

    public function destroyLogo(Request $request, Organization $organization, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $organization);

        $logo = $organization->logo;

        if ($logo) {
            $organization->update(['logo_media_id' => null]);
            $storage->delete($logo);
        }

        return redirect()->route('organizations.edit', $organization)->with('status', 'Organization logo removed.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('organizations.index')->with('status', 'Organization deleted.');
    }
}
