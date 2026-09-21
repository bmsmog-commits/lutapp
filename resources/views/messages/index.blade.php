@extends('layouts.app')

@section('content')
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <h1 style="margin:0;">Messages</h1>
        <a class="btn-primary" href="{{ route('messages.search') }}">New conversation</a>
    </div>

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden;">
        @forelse ($conversations as $conversation)
            @php $other = $conversation->other_participant?->user; @endphp
            <a href="{{ route('messages.show', $conversation) }}" style="display:flex;align-items:center;gap:12px;padding:14px;text-decoration:none;color:inherit;border-bottom:1px solid var(--line);">
                @if ($other?->profile?->profilePhoto)
                    <img src="{{ route('files.show', $other->profile->profilePhoto) }}" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;">
                @else
                    <div style="width:44px;height:44px;border-radius:50%;background:var(--surface);display:grid;place-items:center;font-weight:700;">
                        {{ strtoupper(substr($other?->name ?? '?', 0, 1)) }}
                    </div>
                @endif

                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;gap:8px;">
                        <strong>{{ $other?->name ?? 'Unknown user' }}</strong>
                        @if ($conversation->latestMessage)
                            <span class="muted" style="font-size:13px;">{{ $conversation->latestMessage->created_at->diffForHumans() }}</span>
                        @endif
                    </div>
                    <div class="muted" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        @if ($conversation->latestMessage)
                            {{ $conversation->latestMessage->type === 'file' ? '📎 Attachment' : $conversation->latestMessage->body }}
                        @else
                            No messages yet.
                        @endif
                    </div>
                </div>

                @if ($conversation->unread_count > 0)
                    <span style="background:var(--brand);color:#202124;border-radius:999px;min-width:22px;height:22px;display:grid;place-items:center;font-size:12px;font-weight:700;padding:0 6px;">
                        {{ $conversation->unread_count }}
                    </span>
                @endif
            </a>
        @empty
            <p class="muted" style="padding:14px;">No conversations yet. Start one above.</p>
        @endforelse
    </div>

    <div style="margin-top:16px;">{{ $conversations->links() }}</div>
@endsection
