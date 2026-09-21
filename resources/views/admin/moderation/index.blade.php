@extends('layouts.app')

@section('content')
    <h1>Moderation</h1>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
        @foreach ($statuses as $s)
            <a class="btn{{ $status === $s ? '-primary' : '' }}" href="{{ route('admin.moderation.index', ['status' => $s]) }}">
                {{ ucfirst(str_replace('_', ' ', $s)) }} ({{ $counts[$s] }})
            </a>
        @endforeach
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--line);">
                <th style="padding:8px;">Target</th>
                <th style="padding:8px;">Reason</th>
                <th style="padding:8px;">Status</th>
                <th style="padding:8px;">Reporter</th>
                <th style="padding:8px;">Created</th>
                <th style="padding:8px;">Assigned</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reports as $report)
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:8px;"><a href="{{ route('admin.moderation.reports.show', $report) }}">{{ ucfirst(str_replace('_', ' ', $report->reportable_type)) }}: {{ $report->targetLabel() }}</a></td>
                    <td style="padding:8px;">{{ ucfirst(str_replace('_', ' ', $report->reason)) }}</td>
                    <td style="padding:8px;">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</td>
                    <td style="padding:8px;">{{ $report->reporter->profile?->display_name ?? $report->reporter->name }}</td>
                    <td style="padding:8px;">{{ $report->created_at->format('M j, Y') }}</td>
                    <td style="padding:8px;">{{ $report->assignee?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="padding:8px;">No reports.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $reports->links() }}</div>
@endsection
