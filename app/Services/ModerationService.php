<?php

namespace App\Services;

use App\Models\ModerationAction;
use App\Models\ModerationNote;
use App\Models\Report;
use App\Models\User;

/**
 * The single place reports are created/transitioned and moderation actions
 * are applied — ModerationController and the public report action both stay
 * thin wrappers around this. Centralizing it here is what keeps the audit
 * trail (ModerationAction) actually complete: nothing bypasses it to change
 * a report or an account's moderation state directly.
 */
class ModerationService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    // --- REPORTING ---

    /**
     * @throws \RuntimeException if the reporter already has an open report
     *         against this exact target, or the target no longer exists.
     */
    public function submitReport(User $reporter, string $reportableType, int $reportableId, string $reason, ?string $description): Report
    {
        if (! array_key_exists($reportableType, Report::TARGETS)) {
            throw new \InvalidArgumentException('Unknown report target type.');
        }

        $targetClass = Report::TARGETS[$reportableType];
        $target = $targetClass::find($reportableId);

        if (! $target) {
            throw new \RuntimeException('The reported item could not be found.');
        }

        if ($reportableType === Report::TARGET_USER && $reporter->is($target)) {
            throw new \RuntimeException('You cannot report yourself.');
        }

        $hasOpenReport = Report::where('reporter_id', $reporter->id)
            ->where('reportable_type', $reportableType)
            ->where('reportable_id', $reportableId)
            ->whereIn('status', Report::OPEN_STATUSES)
            ->exists();

        if ($hasOpenReport) {
            throw new \RuntimeException('You already have an open report for this item.');
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $reportableId,
            'reason' => $reason,
            'description' => $description,
            'status' => 'pending',
        ]);

        // Every existing platform moderator, not a single fixed inbox — this
        // is the smallest reasonable "moderator gets notified" behavior
        // without building a queueing/assignment system in this foundation.
        foreach (User::where('is_moderator', true)->get() as $moderator) {
            $this->notifications->notify(
                recipient: $moderator,
                type: 'report.submitted',
                title: 'New report: '.$this->reasonLabel($reason),
                actor: $reporter,
            );
        }

        return $report;
    }

    // --- REPORT LIFECYCLE (moderator-only — enforced by the controller's policy check) ---

    public function assign(Report $report, User $moderator, User $reviewer): Report
    {
        $report->update(['assigned_to' => $reviewer->id, 'status' => $report->status === 'pending' ? 'under_review' : $report->status]);

        $this->logAction($moderator, $report, 'report', $report->id, 'report.assigned', null, null, $reviewer->id);

        return $report->fresh();
    }

    public function updateStatus(Report $report, User $moderator, string $status, ?string $resolution = null): Report
    {
        if (! in_array($status, Report::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid report status.');
        }

        $previous = $report->status;
        $isClosing = in_array($status, ['resolved', 'dismissed'], true);

        $report->update([
            'status' => $status,
            'resolution' => $resolution ?? $report->resolution,
            'resolved_at' => $isClosing ? now() : null,
        ]);

        $this->logAction($moderator, $report, 'report', $report->id, 'report.status_changed', $resolution, $previous, $status);

        if (in_array($status, ['resolved', 'dismissed'], true)) {
            $this->notifications->notify(
                recipient: $report->reporter,
                type: 'report.resolved',
                title: $status === 'resolved' ? 'Your report has been resolved' : 'Your report has been reviewed',
                actor: $moderator,
            );
        }

        return $report->fresh();
    }

    public function addNote(Report $report, User $moderator, string $note): ModerationNote
    {
        return ModerationNote::create([
            'report_id' => $report->id,
            'moderator_id' => $moderator->id,
            'note' => $note,
        ]);
    }

    // --- USER MODERATION ACTIONS ---

    public function warn(User $moderator, User $target, ?string $reason, ?Report $report = null): void
    {
        $this->logAction($moderator, $report, Report::TARGET_USER, $target->id, 'user.warned', $reason);

        $this->notifications->notify(
            recipient: $target,
            type: 'moderation.warned',
            title: 'You have received a warning',
            body: $reason,
            actor: $moderator,
        );
    }

    public function setAccountStatus(User $moderator, User $target, string $status, ?string $reason, ?Report $report = null): void
    {
        if (! in_array($status, ['active', 'restricted', 'suspended', 'deactivated'], true)) {
            throw new \InvalidArgumentException('Invalid account status.');
        }

        $previous = $target->account_status;

        // forceFill bypasses mass-assignment guarding deliberately — this is
        // the ONLY writer of these columns; a user's own profile/settings
        // forms never include them (see User::Fillable).
        $target->forceFill([
            'account_status' => $status,
            'account_status_reason' => $reason,
            'account_status_changed_at' => now(),
        ])->save();

        $this->logAction($moderator, $report, Report::TARGET_USER, $target->id, 'user.status_changed', $reason, $previous, $status);

        if ($status !== $previous) {
            $this->notifications->notify(
                recipient: $target,
                type: 'moderation.account_status_changed',
                title: match ($status) {
                    'active' => 'Your account has been reactivated',
                    'restricted' => 'Your account has been restricted',
                    'suspended' => 'Your account has been suspended',
                    'deactivated' => 'Your account has been deactivated',
                    default => 'Your account status has changed',
                },
                body: $reason,
                actor: $moderator,
            );
        }
    }

    // --- CONTENT MODERATION ---

    /**
     * Forces a piece of content private — reuses each module's own existing
     * visibility column/enforcement (Resource/Job/AudioResource/
     * OrganizationEvent/Organization all already have one, respected
     * everywhere: search, directories, direct-access policies) rather than
     * inventing a second "moderated" visibility concept.
     */
    public function hideContent(User $moderator, string $type, int $id, ?string $reason, ?Report $report = null): void
    {
        $class = Report::TARGETS[$type] ?? null;

        if (! $class || $type === Report::TARGET_USER) {
            throw new \InvalidArgumentException('This target type cannot be hidden as content.');
        }

        $target = $class::find($id);

        if (! $target) {
            throw new \RuntimeException('The content could not be found.');
        }

        $previous = $target->visibility;

        if ($previous !== 'private') {
            $target->update(['visibility' => 'private']);
        }

        if (method_exists($target, 'syncMediaVisibility')) {
            $target->syncMediaVisibility();
        }

        $this->logAction($moderator, $report, $type, $id, 'content.hidden', $reason, $previous, 'private');
    }

    private function logAction(
        User $moderator,
        Report|null $report,
        string $targetType,
        int $targetId,
        string $action,
        ?string $reason,
        ?string $previousState = null,
        ?string $newState = null,
    ): ModerationAction {
        return ModerationAction::create([
            'moderator_id' => $moderator->id,
            'report_id' => $report?->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'reason' => $reason,
            'previous_state' => $previousState,
            'new_state' => $newState,
        ]);
    }

    private function reasonLabel(string $reason): string
    {
        return ucfirst(str_replace('_', ' ', $reason));
    }
}
