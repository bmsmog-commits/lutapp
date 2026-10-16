@props(['title', 'subtitle' => null, 'accent' => 'var(--brand)'])

<header {{ $attributes->merge(['class' => 'page-header']) }}>
    <div style="display:flex;align-items:center;gap:10px;">
        <span aria-hidden="true" style="display:inline-block;width:4px;height:26px;border-radius:999px;background:{{ $accent }};"></span>
        <h1>{{ $title }}</h1>
    </div>
    @if ($subtitle)
        <p style="margin-left:14px;">{{ $subtitle }}</p>
    @endif
    @isset($actions)
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">{{ $actions }}</div>
    @endisset
</header>
