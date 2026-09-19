<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        $this->authorize('viewAny', [Department::class, $organization]);

        return view('organizations.departments.index', [
            'organization' => $organization,
            'departments' => $organization->departments()->withCount('members')->orderBy('name')->paginate(20),
            'canManage' => $request->user()->can('manageMembers', $organization),
        ]);
    }

    public function store(StoreDepartmentRequest $request, Organization $organization): RedirectResponse
    {
        $organization->departments()->create($request->validated());

        return redirect()->route('organizations.departments.index', $organization)->with('status', 'Department created.');
    }

    public function update(UpdateDepartmentRequest $request, Organization $organization, Department $department): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $department);

        $department->update($request->validated());

        return redirect()->route('organizations.departments.index', $organization)->with('status', 'Department updated.');
    }

    public function destroy(Request $request, Organization $organization, Department $department): RedirectResponse
    {
        $this->assertBelongsToOrganization($organization, $department);
        $this->authorize('delete', $department);

        $department->delete();

        return redirect()->route('organizations.departments.index', $organization)->with('status', 'Department deleted.');
    }

    private function assertBelongsToOrganization(Organization $organization, Department $department): void
    {
        abort_unless($department->organization_id === $organization->id, 404);
    }
}
