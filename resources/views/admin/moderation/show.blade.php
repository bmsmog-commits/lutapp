@extends('layouts.app')

@section('content')
    <a class="btn" href="{{ route('admin.moderation.index') }}">&larr; Back to reports</a>

    <h1 style="margin-top:16px;">Report #{{ $report->id }}</h1>

    <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;margin-top:12px;">
        <p><strong>Reporter:</strong> {{ $report->reporter->profile?->display_name ?? $report->reporter->name }} ({{ $report->reporter->email }})</p>
        <p><strong>Target:</strong> {{ ucfirst(str_replace('_', ' ', $report->reportable_type)) }} — {{ $report->targetLabel() }}</p>
        <p><strong>Reason:</strong> {{ ucfirst(str_replace('_', ' ', $report->reason)) }}</p>
        @if ($report->description)
            <p><strong>Description:</strong> {{ $report->description }}</p>
        @endif
        <p><strong>Submitted:</strong> {{ $report->created_at->format('M j, Y g:i A') }}</p>
        <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $report->status)) }}</p>
        <p><strong>Assigned to:</strong> {{ $report->assignee?->name ?? 'Unassigned' }}</p>
        @if ($report->resolution)
            <p><strong>Resolution:</strong> {{ $report->resolution }}</p>
        @endif
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
        <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;flex:1;min-width:260px;">
            <h2>Assign reviewer</h2>
            <form method="post" action="{{ route('admin.moderation.reports.assign', $report) }}">
                @csrf
                <select class="input" name="reviewer_id" required>
                    <option value="">Select moderator</option>
                    @foreach ($moderators as $moderator)
                        <option value="{{ $moderator->id }}" @selected($report->assigned_to === $moderator->id)>{{ $moderator->name }}</option>
                    @endforeach
                </select>
                <button class="btn" type="submit" style="margin-top:8px;">Assign</button>
            </form>
        </div>

        <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;flex:1;min-width:260px;">
            <h2>Update status</h2>
            <form method="post" action="{{ route('admin.moderation.reports.status', $report) }}">
                @csrf
                <select class="input" name="status" required>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" @selected($report->status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
                <textarea name="resolution" placeholder="Resolution notes (optional)" style="margin-top:8px;"></textarea>
                <button class="btn-primary" type="submit" style="margin-top:8px;">Update</button>
            </form>
        </div>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
        @if ($report->reportable_type === \App\Models\Report::TARGET_USER)
            <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;flex:1;min-width:260px;">
                <h2>User actions</h2>
                <form method="post" action="{{ route('admin.moderation.users.warn', $report->reportable_id) }}">
                    @csrf
                    <input type="hidden" name="report_id" value="{{ $report->id }}">
                    <textarea name="reason" placeholder="Warning reason"></textarea>
                    <button class="btn" type="submit" style="margin-top:8px;">Warn user</button>
                </form>

                <form method="post" action="{{ route('admin.moderation.users.status', $report->reportable_id) }}" style="margin-top:12px;">
                    @csrf
                    <input type="hidden" name="report_id" value="{{ $report->id }}">
                    <select class="input" name="status" required>
                        <option value="active">Active</option>
                        <option value="restricted">Restricted</option>
                        <option value="suspended">Suspended</option>
                        <option value="deactivated">Deactivated</option>
                    </select>
                    <textarea name="reason" placeholder="Reason" style="margin-top:8px;"></textarea>
                    <button class="btn-danger" type="submit" style="margin-top:8px;">Update account status</button>
                </form>
            </div>
        @else
            <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;flex:1;min-width:260px;">
                <h2>Content actions</h2>
                <form method="post" action="{{ route('admin.moderation.content.hide') }}">
                    @csrf
                    <input type="hidden" name="target_type" value="{{ $report->reportable_type }}">
                    <input type="hidden" name="target_id" value="{{ $report->reportable_id }}">
                    <input type="hidden" name="report_id" value="{{ $report->id }}">
                    <textarea name="reason" placeholder="Reason"></textarea>
                    <button class="btn-danger" type="submit" style="margin-top:8px;">Hide content</button>
                </form>
            </div>
        @endif

        <div class="panel" style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;flex:1;min-width:260px;">
            <h2>Moderation notes (internal)</h2>
            @foreach ($report->notes as $note)
                <div style="border-top:1px solid var(--line);padding:8px 0;">
                    <div class="muted" style="font-size:12px;">{{ $note->moderator->name }} &middot; {{ $note->created_at->diffForHumans() }}</div>
                    <div>{{ $note->note }}</div>
                </div>
            @endforeach
            <form method="post" action="{{ route('admin.moderation.reports.notes.store', $report) }}" style="margin-top:8px;">
                @csrf
                <textarea name="note" placeholder="Add an internal note" required></textarea>
                <button class="btn" type="submit" style="margin-top:8px;">Add note</button>
            </form>
        </div>
    </div>
@endsection
