<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// Every route here sits behind the 'moderator' middleware (bootstrap/app.php)
// — User::isModerator() only, never an organization role. See
// EnsureIsModerator and the migration comment on users.is_moderator.
class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        $query = Report::query()->with(['reporter.profile', 'assignee'])->latest();

        if (in_array($status, Report::STATUSES, true)) {
            $query->where('status', $status);
        }

        return view('admin.moderation.index', [
            'reports' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'statuses' => Report::STATUSES,
            'counts' => collect(Report::STATUSES)->mapWithKeys(fn ($s) => [$s => Report::where('status', $s)->count()]),
        ]);
    }

    public function show(Report $report): View
    {
        return view('admin.moderation.show', [
            'report' => $report->load(['reporter.profile', 'assignee', 'notes.moderator']),
            'moderators' => User::where('is_moderator', true)->get(),
            'statuses' => Report::STATUSES,
        ]);
    }

    public function assign(Request $request, Report $report, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate(['reviewer_id' => ['required', 'exists:users,id']]);
        $reviewer = User::findOrFail($data['reviewer_id']);

        abort_unless($reviewer->isModerator(), 422, 'The assigned reviewer must be a moderator.');

        $moderation->assign($report, $request->user(), $reviewer);

        return back()->with('status', 'Report assigned.');
    }

    public function updateStatus(Request $request, Report $report, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Report::STATUSES)],
            'resolution' => ['nullable', 'string', 'max:3000'],
        ]);

        $moderation->updateStatus($report, $request->user(), $data['status'], $data['resolution'] ?? null);

        return back()->with('status', 'Report status updated.');
    }

    public function addNote(Request $request, Report $report, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);

        $moderation->addNote($report, $request->user(), $data['note']);

        return back()->with('status', 'Note added.');
    }

    public function warnUser(Request $request, User $user, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000'], 'report_id' => ['nullable', 'exists:reports,id']]);

        $moderation->warn($request->user(), $user, $data['reason'] ?? null, $this->reportOrNull($data));

        return back()->with('status', 'User warned.');
    }

    public function updateUserStatus(Request $request, User $user, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'restricted', 'suspended', 'deactivated'])],
            'reason' => ['nullable', 'string', 'max:1000'],
            'report_id' => ['nullable', 'exists:reports,id'],
        ]);

        $moderation->setAccountStatus($request->user(), $user, $data['status'], $data['reason'] ?? null, $this->reportOrNull($data));

        return back()->with('status', 'Account status updated.');
    }

    public function hideContent(Request $request, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(array_diff(array_keys(Report::TARGETS), [Report::TARGET_USER]))],
            'target_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'report_id' => ['nullable', 'exists:reports,id'],
        ]);

        $moderation->hideContent($request->user(), $data['target_type'], $data['target_id'], $data['reason'] ?? null, $this->reportOrNull($data));

        return back()->with('status', 'Content hidden.');
    }

    private function reportOrNull(array $data): ?Report
    {
        return isset($data['report_id']) ? Report::find($data['report_id']) : null;
    }
}
