@extends('layouts.app')

@section('content')
    <h1>Edit audio</h1>

    <form method="post" action="{{ route('audio.update', $audio) }}">
        @csrf
        @method('PUT')
        @include('audio._form', ['audio' => $audio])
        <button class="btn-primary" type="submit" style="margin-top:16px;">Save changes</button>
    </form>

    <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:16px;display:flex;flex-direction:column;gap:16px;">
        <form method="post" action="{{ route('audio.file.store', $audio) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
            @csrf
            <input type="file" name="audio" accept="audio/*">
            <button class="btn" type="submit">Upload/replace audio file</button>
        </form>

        <div>
            <form method="post" action="{{ route('audio.cover.store', $audio) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
                @csrf
                <input type="file" name="cover" accept="image/*">
                <button class="btn" type="submit">Upload cover</button>
            </form>
            @if ($audio->cover)
                <form method="post" action="{{ route('audio.cover.destroy', $audio) }}" style="margin-top:8px;">
                    @csrf @method('DELETE')
                    <button class="btn-danger" type="submit">Remove cover</button>
                </form>
            @endif
        </div>
    </div>
@endsection
