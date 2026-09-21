@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h1 style="margin:0;">Notifications</h1>
        @if ($unreadCount > 0)
            <form method="post" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn" type="submit">Mark all as read</button>
            </form>
        @endif
    </div>

    <div style="display:flex;gap:8px;margin:12px 0;">
        <a class="btn{{ $filter === 'all' ? '-primary' : '' }}" href="{{ route('notifications.index') }}">All</a>
        <a class="btn{{ $filter === 'unread' ? '-primary' : '' }}" href="{{ route('notifications.index', ['filter' => 'unread']) }}">Unread</a>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px;">
        @forelse ($notifications as $notification)
            <div style="border:1px solid var(--line);border-radius:8px;background:{{ $notification->isRead() ? '#fff' : '#fffbea' }};padding:14px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                <div>
                    <strong>{{ $notification->title }}</strong>
                    @if ($notification->body)
                        <div class="muted">{{ $notification->body }}</div>
                    @endif
                    <div class="muted" style="font-size:12px;margin-top:4px;">{{ $notification->created_at->diffForHumans() }}</div>
                </div>

                @unless ($notification->isRead())
                    <form method="post" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        <button class="btn" type="submit">
                            {{ $notification->relatedUrl(auth()->user()) ? 'Open' : 'Mark read' }}
                        </button>
                    </form>
                @else
                    @if ($url = $notification->relatedUrl(auth()->user()))
                        <a class="btn" href="{{ $url }}">Open</a>
                    @endif
                @endunless
            </div>
        @empty
            <p class="muted">No notifications yet.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $notifications->links() }}</div>
@endsection
