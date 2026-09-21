@extends('layouts.app')

@section('content')
    @php $other = $otherParticipant?->user; @endphp

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <a class="btn" href="{{ route('messages.index') }}">&larr; Back</a>
        <h1 style="margin:0;">{{ $other?->name ?? 'Conversation' }}</h1>
    </div>

    @if ($messages->currentPage() < $messages->lastPage())
        <div style="text-align:center;margin-bottom:12px;">
            <a class="btn" href="{{ route('messages.show', array_merge(['conversation' => $conversation], ['page' => $messages->currentPage() + 1])) }}">Load older messages</a>
        </div>
    @endif

    <div style="border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;display:flex;flex-direction:column;gap:12px;min-height:320px;">
        @forelse ($messages as $message)
            @php $mine = $message->sender_id === auth()->id(); @endphp
            <div style="align-self:{{ $mine ? 'flex-end' : 'flex-start' }};max-width:75%;">
                <div style="background:{{ $mine ? 'var(--brand)' : 'var(--surface)' }};border-radius:10px;padding:10px 12px;">
                    @if (! $mine)
                        <div class="muted" style="font-size:12px;margin-bottom:2px;">{{ $message->sender->name }}</div>
                    @endif

                    @if ($message->trashed())
                        <em class="muted">Message deleted.</em>
                    @else
                        @if ($message->body)
                            <div>{{ $message->body }}</div>
                        @endif

                        @if ($message->attachment)
                            <div style="margin-top:6px;">
                                <a href="{{ route('files.show', $message->attachment) }}">📎 {{ $message->attachment->original_name }}</a>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="muted" style="font-size:11px;margin-top:2px;{{ $mine ? 'text-align:right;' : '' }}">
                    {{ $message->created_at->format('M j, g:i A') }}
                    @if ($message->edited_at) (edited) @endif

                    @if ($mine && ! $message->trashed())
                        <form method="post" action="{{ route('messages.messages.destroy', [$conversation, $message]) }}" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                            @csrf @method('DELETE')
                            <button type="submit" style="border:none;background:none;padding:0;color:var(--danger);font:inherit;cursor:pointer;">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="muted">No messages yet. Say hello.</p>
        @endforelse
    </div>

    <form method="post" action="{{ route('messages.messages.store', $conversation) }}" enctype="multipart/form-data" style="margin-top:16px;display:flex;gap:8px;align-items:flex-start;">
        @csrf
        <textarea name="body" placeholder="Write a message..." style="min-height:44px;flex:1;"></textarea>
        <input type="file" name="attachment" style="max-width:160px;">
        <button class="btn-primary" type="submit">Send</button>
    </form>
@endsection
