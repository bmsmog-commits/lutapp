<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request, ModerationService $moderation): RedirectResponse
    {
        $data = $request->validate([
            'reportable_type' => ['required', Rule::in(array_keys(Report::TARGETS))],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(Report::REASONS)],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);

        try {
            $moderation->submitReport(
                $request->user(),
                $data['reportable_type'],
                $data['reportable_id'],
                $data['reason'],
                $data['description'] ?? null,
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withErrors(['report' => $e->getMessage()]);
        }

        return back()->with('status', 'Thank you — your report has been submitted for review.');
    }
}
