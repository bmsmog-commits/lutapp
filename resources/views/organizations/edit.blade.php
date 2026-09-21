@extends('layouts.app')

@section('content')
    <h1>Edit {{ $organization->name }}</h1>

    <div style="max-width:520px;margin-bottom:20px;">
        @if ($organization->logo)
            <img src="{{ route('files.show', $organization->logo) }}" alt="Organization logo" style="width:96px;height:96px;border-radius:8px;object-fit:cover;margin-bottom:12px;">
        @else
            <div style="width:96px;height:96px;border-radius:8px;background:var(--surface);border:1px solid var(--line);display:grid;place-items:center;color:var(--muted);margin-bottom:12px;">No logo</div>
        @endif

        <form method="post" action="{{ route('organizations.logo.store', $organization) }}" enctype="multipart/form-data" style="margin-bottom:6px;">
            @csrf
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required>
            <button class="btn" type="submit">Upload logo</button>
        </form>
        @if ($organization->logo)
            <form method="post" action="{{ route('organizations.logo.destroy', $organization) }}">
                @csrf
                @method('delete')
                <button class="btn-danger" type="submit" onclick="return confirm('Remove the organization logo?')">Remove logo</button>
            </form>
        @endif
    </div>

    <form method="post" action="{{ route('organizations.update', $organization) }}" style="max-width:520px;display:grid;gap:12px;">
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
            <label for="visibility">Visibility</label>
            <select class="input" id="visibility" name="visibility">
                <option value="public" @selected(old('visibility', $organization->visibility) === 'public')>Public</option>
                <option value="private" @selected(old('visibility', $organization->visibility) === 'private')>Private</option>
            </select>
        </div>

        <button class="btn-primary" type="submit">Save changes</button>
    </form>
@endsection
