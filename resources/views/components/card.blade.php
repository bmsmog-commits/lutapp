@props([
    'title' => null,
    'accent' => null,
    'href' => null,
    'link' => null,
    'linkLabel' => null,
])

@php
    $style = $accent ? 'border-top:3px solid '.$accent.';' : '';
@endphp

<div {{ $attributes->merge(['class' => 'panel']) }} style="{{ $style }}">
    @if ($title)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
            <h2 style="margin:0;font-size:16px;">
                @if ($href)<a href="{{ $href }}" style="text-decoration:none;">{{ $title }}</a>@else{{ $title }}@endif
            </h2>
            @if ($link)
                <a href="{{ $link }}" class="muted" style="font-size:13px;text-decoration:none;">{{ $linkLabel ?? 'View all' }} &rarr;</a>
            @endif
        </div>
    @endif
    {{ $slot }}
</div>
