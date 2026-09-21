@extends('layouts.app')

@section('content')
    <h1>Add resource{{ $organization ? ' to '.$organization->name : '' }}</h1>

    <form method="post" action="{{ $organization ? route('organizations.resources.store', $organization) : route('resources.store') }}" style="max-width:520px;display:grid;gap:12px;">
        @csrf

        <div class="field">
            <label for="title">Title</label>
            <input class="input" id="title" name="title" value="{{ old('title') }}" required>
        </div>

        <div class="field">
            <label for="author">Author</label>
            <input class="input" id="author" name="author" value="{{ old('author') }}">
        </div>

        <div class="field">
            <label for="publisher">Publisher</label>
            <input class="input" id="publisher" name="publisher" value="{{ old('publisher') }}">
        </div>

        <div class="field">
            <label for="published_on">Publication date</label>
            <input class="input" id="published_on" name="published_on" type="date" value="{{ old('published_on') }}">
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </div>

        <div class="field">
            <label for="category">Category</label>
            <select class="input" id="category" name="category">
                <option value="">— None —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="language_id">Language</label>
            <select class="input" id="language_id" name="language_id">
                <option value="">— None —</option>
                @foreach ($languages as $language)
                    <option value="{{ $language->id }}" @selected((string) old('language_id') === (string) $language->id)>{{ $language->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="visibility">Visibility</label>
            <select class="input" id="visibility" name="visibility">
                <option value="private" @selected(old('visibility', 'private') === 'private')>Private</option>
                <option value="public" @selected(old('visibility') === 'public')>Public</option>
            </select>
            @if ($organization && $organization->visibility !== 'public')
                <p class="muted">This organization is private, so the resource will be kept private regardless of this setting.</p>
            @endif
        </div>

        <button class="btn-primary" type="submit">Create resource</button>
    </form>
@endsection
