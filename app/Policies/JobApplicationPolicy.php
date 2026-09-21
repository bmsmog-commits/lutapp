<?php

namespace App\Policies;

use App\Models\JobApplication;
use App\Models\User;

class JobApplicationPolicy
{
    public function view(User $user, JobApplication $application): bool
    {
        return $application->applicant_id === $user->id
            || app(JobPolicy::class)->isManager($user, $application->job);
    }

    // Only the applicant may withdraw their own application.
    public function withdraw(User $user, JobApplication $application): bool
    {
        return $application->applicant_id === $user->id;
    }

    // Only the job's owner/manager may accept or reject.
    public function decide(User $user, JobApplication $application): bool
    {
        return app(JobPolicy::class)->isManager($user, $application->job);
    }
}
