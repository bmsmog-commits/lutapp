@props([
    'title',
    'icon' => '◎',
    'accent' => 'var(--navy)',
    'tint' => 'var(--navy-soft)',
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <span class="empty-icon" style="background:{{ $tint }};color:{{ $accent }};" aria-hidden="true">{{ $icon }}</span>
    <h3>{{ $title }}</h3>
    <div>{{ $slot }}</div>
    @isset($actions)
        <div style="margin-top:18px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">{{ $actions }}</div>
    @endisset
</div>
