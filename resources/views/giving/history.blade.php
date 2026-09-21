@extends('layouts.app')

@section('content')
    <h1>Giving history — {{ $campaign->title }}</h1>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--line);">
                <th style="padding:8px;">Donor</th>
                <th style="padding:8px;">Amount</th>
                <th style="padding:8px;">Status</th>
                <th style="padding:8px;">Reference</th>
                <th style="padding:8px;">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donations as $donation)
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:8px;">{{ $donation->donorDisplayName() }}</td>
                    <td style="padding:8px;">{{ $donation->currency }} {{ \App\Support\Money::toDecimalString($donation->amount) }}</td>
                    <td style="padding:8px;">{{ ucfirst($donation->status) }}</td>
                    <td style="padding:8px;">{{ $donation->reference }}</td>
                    <td style="padding:8px;">{{ $donation->created_at->format('M j, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="padding:8px;">No donations yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $donations->links() }}</div>
@endsection
