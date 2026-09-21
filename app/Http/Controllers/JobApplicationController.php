<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobApplicationController extends Controller
{
    public function index(Request $request, Job $job): View
    {
        $this->authorize('viewApplications', $job);

        $applications = $job->applications()->with('applicant.profile')->latest()->paginate(20);

        return view('jobs.applications.index', ['job' => $job, 'applications' => $applications]);
    }

    public function mine(Request $request): View
    {
        $applications = $request->user()->jobApplications()->with(['job.organization', 'job.user'])->latest()->paginate(20);

        return view('jobs.applications.mine', ['applications' => $applications]);
    }

    public function store(Request $request, Job $job, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('apply', $job);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:3000'],
        ]);

        $hasActiveApplication = $job->applications()
            ->where('applicant_id', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->exists();

        if ($hasActiveApplication) {
            return back()->withErrors(['message' => 'You already have an active application for this job.']);
        }

        $application = $job->applications()->create([
            'applicant_id' => $request->user()->id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        if ($owner = $job->ownerUser()) {
            $notifications->notify(
                recipient: $owner,
                type: 'job.application.created',
                title: $request->user()->name.' applied for "'.$job->title.'"',
                actor: $request->user(),
                relatedType: Notification::RELATED_JOB_APPLICATION,
                relatedId: $application->id,
            );
        }

        return redirect()->route('jobs.show', $job)->with('status', 'Application submitted.');
    }

    public function show(Request $request, Job $job, JobApplication $application): View
    {
        $this->assertBelongsToJob($job, $application);
        $this->authorize('view', $application);

        return view('jobs.applications.show', [
            'job' => $job,
            'application' => $application->load('applicant.profile'),
        ]);
    }

    public function withdraw(Request $request, Job $job, JobApplication $application, NotificationService $notifications): RedirectResponse
    {
        $this->assertBelongsToJob($job, $application);
        $this->authorize('withdraw', $application);

        $application->update(['status' => 'withdrawn']);

        if ($owner = $job->ownerUser()) {
            $notifications->notify(
                recipient: $owner,
                type: 'job.application.withdrawn',
                title: $application->applicant->name.' withdrew their application for "'.$job->title.'"',
                actor: $request->user(),
                relatedType: Notification::RELATED_JOB_APPLICATION,
                relatedId: $application->id,
            );
        }

        return redirect()->route('jobs.applications.mine')->with('status', 'Application withdrawn.');
    }

    public function accept(Request $request, Job $job, JobApplication $application, NotificationService $notifications): RedirectResponse
    {
        $this->assertBelongsToJob($job, $application);
        $this->authorize('decide', $application);

        $owner = $job->ownerUser();
        $conversation = $owner ? Conversation::findOrCreateDirect($owner, $application->applicant) : null;

        $application->update([
            'status' => 'accepted',
            'conversation_id' => $conversation?->id,
        ]);

        $notifications->notify(
            recipient: $application->applicant,
            type: 'job.application.accepted',
            title: 'Your application for "'.$job->title.'" was accepted',
            actor: $request->user(),
            relatedType: Notification::RELATED_JOB_APPLICATION,
            relatedId: $application->id,
        );

        return redirect()->route('jobs.applications.index', $job)->with('status', 'Application accepted.');
    }

    public function reject(Request $request, Job $job, JobApplication $application, NotificationService $notifications): RedirectResponse
    {
        $this->assertBelongsToJob($job, $application);
        $this->authorize('decide', $application);

        $application->update(['status' => 'rejected']);

        $notifications->notify(
            recipient: $application->applicant,
            type: 'job.application.rejected',
            title: 'Your application for "'.$job->title.'" was not accepted',
            actor: $request->user(),
            relatedType: Notification::RELATED_JOB_APPLICATION,
            relatedId: $application->id,
        );

        return redirect()->route('jobs.applications.index', $job)->with('status', 'Application rejected.');
    }

    private function assertBelongsToJob(Job $job, JobApplication $application): void
    {
        abort_unless($application->job_id === $job->id, 404);
    }
}
