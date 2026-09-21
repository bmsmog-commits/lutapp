<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Organization;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobController extends Controller
{
    // Public discovery is open to guests for public published jobs, mirroring
    // the resource library — scopeVisibleTo() does the actual filtering.
    public function index(Request $request): View
    {
        $query = Job::query()->visibleTo($request->user())->with(['organization', 'user']);

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($workMode = $request->query('work_mode')) {
            $query->where('work_mode', $workMode);
        }

        if ($country = $request->query('country')) {
            $query->where('country', $country);
        }

        if ($city = $request->query('city')) {
            $query->where('city', 'like', "%{$city}%");
        }

        return view('jobs.index', [
            'jobs' => $query->latest()->paginate(12)->withQueryString(),
            'categories' => Job::CATEGORIES,
            'workModes' => Job::WORK_MODES,
            'filters' => $request->only(['q', 'category', 'work_mode', 'country', 'city']),
        ]);
    }

    public function mine(Request $request): View
    {
        $jobs = Job::query()
            ->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhereIn('organization_id', $request->user()->ownedOrganizations()->pluck('id'));
            })
            ->with(['organization', 'user'])
            ->withCount('applications')
            ->latest()
            ->paginate(12);

        return view('jobs.mine', ['jobs' => $jobs]);
    }

    public function create(Request $request, ?Organization $organization = null): View
    {
        $this->authorize('create', Job::class);

        if ($organization) {
            $this->authorize('manageMembers', $organization);
        }

        return view('jobs.create', [
            'organization' => $organization,
            'categories' => Job::CATEGORIES,
            'workModes' => Job::WORK_MODES,
            'budgetTypes' => Job::BUDGET_TYPES,
        ]);
    }

    public function store(Request $request, ?Organization $organization = null): RedirectResponse
    {
        $this->authorize('create', Job::class);

        if ($organization) {
            $this->authorize('manageMembers', $organization);
        }

        $data = $this->validateJob($request);

        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = 'draft';

        if ($organization) {
            $data['organization_id'] = $organization->id;
            // A job can never be more public than its owning organization —
            // same ceiling rule already established for resources/logos.
            $data['visibility'] = $organization->visibility === 'public' ? $data['visibility'] : 'private';
        } else {
            $data['user_id'] = $request->user()->id;
        }

        $job = Job::create($data);

        return redirect()->route('jobs.show', $job)->with('status', 'Job saved as a draft.');
    }

    public function show(Request $request, Job $job): View
    {
        $this->authorize('view', $job);

        $user = $request->user();

        return view('jobs.show', [
            'job' => $job->load(['organization', 'user', 'attachment']),
            'canManage' => $user?->can('update', $job) ?? false,
            'canApply' => $user?->can('apply', $job) ?? false,
            'myApplication' => $user ? $job->applications()->where('applicant_id', $user->id)->latest()->first() : null,
        ]);
    }

    public function edit(Request $request, Job $job): View
    {
        $this->authorize('update', $job);

        return view('jobs.edit', [
            'job' => $job,
            'categories' => Job::CATEGORIES,
            'workModes' => Job::WORK_MODES,
            'budgetTypes' => Job::BUDGET_TYPES,
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $data = $this->validateJob($request);

        if ($job->organization_id && $job->organization->visibility !== 'public') {
            $data['visibility'] = 'private';
        }

        $job->update($data);
        $job->syncMediaVisibility();

        return redirect()->route('jobs.show', $job)->with('status', 'Job updated.');
    }

    public function publish(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update(['status' => 'published']);
        $job->syncMediaVisibility();

        return redirect()->route('jobs.show', $job)->with('status', 'Job published.');
    }

    public function close(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update(['status' => 'closed']);
        $job->syncMediaVisibility();

        return redirect()->route('jobs.show', $job)->with('status', 'Job closed.');
    }

    public function cancel(Request $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->update(['status' => 'cancelled']);
        $job->syncMediaVisibility();

        return redirect()->route('jobs.show', $job)->with('status', 'Job cancelled.');
    }

    public function destroy(Request $request, Job $job, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('delete', $job);

        if ($job->attachment) {
            $storage->delete($job->attachment);
        }

        $job->delete();

        return redirect()->route('jobs.mine')->with('status', 'Job deleted.');
    }

    public function storeAttachment(Request $request, Job $job, MediaStorageService $storage): RedirectResponse
    {
        $this->authorize('update', $job);

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:'.config('media.max_size_kb.document')],
        ]);

        $old = $job->attachment;

        try {
            $owner = $job->organization_id ? ['organization_id' => $job->organization_id] : ['user_id' => $job->user_id];
            $new = $storage->store($request->file('attachment'), $owner, $job->effectiveMediaVisibility());
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['attachment' => $e->getMessage()]);
        }

        $job->update(['attachment_media_id' => $new->id]);

        if ($old) {
            $storage->delete($old);
        }

        return redirect()->route('jobs.show', $job)->with('status', 'Attachment updated.');
    }

    private function validateJob(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(Job::CATEGORIES)],
            'visibility' => ['required', Rule::in(Job::VISIBILITIES)],
            'work_mode' => ['required', Rule::in(Job::WORK_MODES)],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'budget_type' => ['nullable', Rule::in(Job::BUDGET_TYPES)],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'currency' => ['nullable', 'string', 'max:10'],
            'application_deadline' => ['nullable', 'date'],
        ]);

        if (($data['budget_type'] ?? null) === 'negotiable') {
            $data['budget_min'] = null;
            $data['budget_max'] = null;
        } elseif (($data['budget_type'] ?? null) === 'fixed') {
            $data['budget_max'] = $data['budget_min'] ?? null;
        }

        return $data;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'job';
        $slug = $base;
        $suffix = 1;

        while (Job::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
