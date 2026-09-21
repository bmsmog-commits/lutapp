@extends('layouts.app')

@section('content')
    <h1>Search Lutapp</h1>

    <form method="get" action="{{ route('search.index') }}" style="display:flex;gap:8px;margin-bottom:12px;">
        <input type="hidden" name="type" value="{{ $type }}">
        <input class="input" type="text" name="q" value="{{ $query }}" placeholder="Search Lutapp..." autofocus style="max-width:320px;">
        <button class="btn-primary" type="submit">Search</button>
    </form>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <a class="btn{{ $type === 'all' ? '-primary' : '' }}" href="{{ route('search.index', ['q' => $query, 'type' => 'all']) }}">All</a>
        @foreach ($providerKeys as $key)
            <a class="btn{{ $type === $key ? '-primary' : '' }}" href="{{ route('search.index', ['q' => $query, 'type' => $key]) }}">{{ $labels[$key] }}</a>
        @endforeach
    </div>

    @if ($type !== 'all')
        <form method="get" action="{{ route('search.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            <input type="hidden" name="q" value="{{ $query }}">
            <input type="hidden" name="type" value="{{ $type }}">

            @if (in_array($type, ['jobs', 'events', 'resources', 'audio']))
                <input class="input" type="text" name="category" value="{{ request('category') }}" placeholder="Category" style="max-width:160px;">
            @endif
            @if ($type === 'jobs')
                <input class="input" type="text" name="work_mode" value="{{ request('work_mode') }}" placeholder="Work mode" style="max-width:140px;">
                <input class="input" type="text" name="city" value="{{ request('city') }}" placeholder="City" style="max-width:140px;">
            @endif
            @if ($type === 'events')
                <input class="input" type="text" name="location_mode" value="{{ request('location_mode') }}" placeholder="Location mode" style="max-width:150px;">
                <input class="input" type="date" name="date" value="{{ request('date') }}" style="max-width:160px;">
            @endif
            @if (in_array($type, ['resources', 'audio']))
                <input class="input" type="text" name="language_id" value="{{ request('language_id') }}" placeholder="Language ID" style="max-width:140px;">
            @endif
            @if ($type === 'audio')
                <input class="input" type="text" name="creator" value="{{ request('creator') }}" placeholder="Creator" style="max-width:140px;">
            @endif
            @if ($type === 'organizations')
                <input class="input" type="text" name="type_filter" value="{{ request('type_filter') }}" placeholder="Type" style="max-width:140px;">
                <input class="input" type="text" name="country" value="{{ request('country') }}" placeholder="Country" style="max-width:140px;">
                <input class="input" type="text" name="state" value="{{ request('state') }}" placeholder="State" style="max-width:140px;">
                <input class="input" type="text" name="city" value="{{ request('city') }}" placeholder="City" style="max-width:140px;">
            @endif
            @if ($type === 'bible')
                <input class="input" type="text" name="translation_id" value="{{ request('translation_id') }}" placeholder="Translation ID" style="max-width:140px;">
                <input class="input" type="text" name="book_id" value="{{ request('book_id') }}" placeholder="Book ID" style="max-width:120px;">
            @endif
            @if ($type === 'people')
                <input class="input" type="text" name="country" value="{{ request('country') }}" placeholder="Country" style="max-width:140px;">
                <input class="input" type="text" name="city" value="{{ request('city') }}" placeholder="City" style="max-width:140px;">
                <input class="input" type="text" name="language" value="{{ request('language') }}" placeholder="Language code" style="max-width:140px;">
            @endif

            <button class="btn" type="submit">Apply filters</button>
        </form>
    @endif

    @if ($query === '')
        <p class="muted">Type at least 2 characters to search.</p>
    @elseif ($type === 'all')
        @php $hasAny = collect($grouped)->some(fn ($items) => $items->isNotEmpty()); @endphp

        @if (! $hasAny)
            <p class="muted">No results found for "{{ $query }}".</p>
        @else
            @foreach ($grouped as $key => $items)
                @continue($items->isEmpty())
                <h2 style="margin-top:24px;">{{ $labels[$key] }}</h2>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($items as $result)
                        @include('search._result', ['result' => $result])
                    @endforeach
                </div>
            @endforeach
        @endif
    @else
        @if ($results->isEmpty())
            <p class="muted">No results found for "{{ $query }}".</p>
        @else
            <div style="display:flex;flex-direction:column;gap:8px;">
                @foreach ($results as $result)
                    @include('search._result', ['result' => $result])
                @endforeach
            </div>
            <div style="margin-top:16px;">{{ $results->links() }}</div>
        @endif
    @endif
@endsection
