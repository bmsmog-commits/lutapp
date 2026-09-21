@extends('layouts.app')

@section('content')
    <h1>Edit event</h1>

    <form method="post" action="{{ route('org-events.update', $event) }}">
        @csrf
        @method('PUT')
        @include('org-events._form', ['event' => $event])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save changes</button>
    </form>

    <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:16px;">
        <form method="post" action="{{ route('org-events.cover.store', $event) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
            @csrf
            <input type="file" name="cover">
            <button class="btn" type="submit">Upload cover</button>
        </form>
        @if ($event->cover)
            <form method="post" action="{{ route('org-events.cover.destroy', $event) }}" style="margin-top:8px;">
                @csrf @method('DELETE')
                <button class="btn-danger" type="submit">Remove cover</button>
            </form>
        @endif
    </div>
@endsection
