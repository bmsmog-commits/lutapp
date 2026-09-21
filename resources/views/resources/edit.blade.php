@extends('layouts.app')

@section('content')
    <h1>Edit {{ $resource->title }}</h1>

    <form method="post" action="{{ route('resources.update', $resource) }}" style="max-width:520px;display:grid;gap:12px;">
        @csrf
        @method('put')

        <div class="field">
            <label for="title">Title</label>
            <input class="input" id="title" name="title" value="{{ old('title', $resource->title) }}" required>
        </div>

        <div class="field">
            <label for="author">Author</label>
            <input class="input" id="author" name="author" value="{{ old('author', $resource->author) }}">
        </div>

        <div class="field">
            <label for="publisher">Publisher</label>
            <input class="input" id="publisher" name="publisher" value="{{ old('publisher', $resource->publisher) }}">
        </div>

        <div class="field">
            <label for="published_on">Publication date</label>
            <input class="input" id="published_on" name="published_on" type="date" value="{{ old('published_on', $resource->published_on?->format('Y-m-d')) }}">
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $resource->description) }}</textarea>
        </div>

        <div class="field">
            <label for="category">Category</label>
            <select class="input" id="category" name="category">
                <option value="">— None —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(old('category', $resource->category) === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="language_id">Language</label>
            <select class="input" id="language_id" name="language_id">
                <option value="">— None —</option>
                @foreach ($languages as $language)
                    <option value="{{ $language->id }}" @selected((string) old('language_id', $resource->language_id) === (string) $language->id)>{{ $language->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="visibility">Visibility</label>
            <select class="input" id="visibility" name="visibility">
                <option value="private" @selected(old('visibility', $resource->visibility) === 'private')>Private</option>
                <option value="public" @selected(old('visibility', $resource->visibility) === 'public')>Public</option>
            </select>
        </div>

        <button class="btn-primary" type="submit">Save changes</button>
    </form>
@endsection
