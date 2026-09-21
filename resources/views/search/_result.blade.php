@php /** @var \App\Support\Search\SearchResult $result */ @endphp
<a href="{{ $result->url }}" style="display:flex;gap:12px;align-items:center;border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px;text-decoration:none;color:inherit;">
    @if ($result->image)
        <img src="{{ $result->image }}" alt="" style="width:48px;height:48px;border-radius:6px;object-fit:cover;flex-shrink:0;">
    @else
        <div style="width:48px;height:48px;border-radius:6px;background:var(--surface);display:grid;place-items:center;font-weight:700;flex-shrink:0;">
            {{ strtoupper(substr($result->title, 0, 1)) }}
        </div>
    @endif
    <div style="min-width:0;flex:1;">
        <strong>{{ $result->title }}</strong>
        @if ($result->subtitle)
            <div class="muted">{{ $result->subtitle }}</div>
        @endif
        @if ($result->description)
            <div class="muted" style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $result->description }}</div>
        @endif
    </div>
    @if ($result->type === 'people' && isset($followingIds) && in_array($result->id, $followingIds, true))
        <span class="muted" style="font-size:12px;border:1px solid var(--line);border-radius:999px;padding:3px 8px;flex-shrink:0;">Following</span>
    @endif
</a>
