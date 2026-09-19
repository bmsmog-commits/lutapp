@extends('layouts.app')

@section('content')
    <h1>Edit {{ $organization->name }}</h1>

    <form method="post" action="{{ route('organizations.update', $organization) }}" enctype="multipart/form-data" style="max-width:520px;display:grid;gap:12px;">
        @csrf
        @method('put')

        <div class="field">
            <label for="name">Name</label>
            <input class="input" id="name" name="name" value="{{ old('name', $organization->name) }}" required>
        </div>

        <div class="field">
            <label for="slug">Slug</label>
            <input class="input" id="slug" name="slug" value="{{ old('slug', $organization->slug) }}" required>
        </div>

        <div class="field">
            <label for="type">Type</label>
            <select class="input" id="type" name="type" required>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('type', $organization->type) === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description', $organization->description) }}</textarea>
        </div>

        <div class="field">
            <label for="logo">Logo</label>
            <input class="input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
        </div>

        <div class="field">
            <label for="visibility">Visibility</label>
            <select class="input" id="visibility" name="visibility">
                <option value="public" @selected(old('visibility', $organization->visibility) === 'public')>Public</option>
                <option value="private" @selected(old('visibility', $organization->visibility) === 'private')>Private</option>
            </select>
        </div>

        <button class="btn-primary" type="submit">Save changes</button>
    </form>
@endsection
