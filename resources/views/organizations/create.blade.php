@extends('layouts.app')

@section('content')
    <h1>Create organization</h1>

    <form method="post" action="{{ route('organizations.store') }}" style="max-width:520px;display:grid;gap:12px;">
        @csrf

        <div class="field">
            <label for="name">Name</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required>
        </div>

        <div class="field">
            <label for="slug">Slug</label>
            <input class="input" id="slug" name="slug" value="{{ old('slug') }}" required>
        </div>

        <div class="field">
            <label for="type">Type</label>
            <select class="input" id="type" name="type" required>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="description">Description</label>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}">
        </div>

        <div class="field">
            <label for="phone">Phone</label>
            <input class="input" id="phone" name="phone" value="{{ old('phone') }}">
        </div>

        <div class="field">
            <label for="visibility">Visibility</label>
            <select class="input" id="visibility" name="visibility">
                <option value="public" @selected(old('visibility') === 'public')>Public</option>
                <option value="private" @selected(old('visibility') === 'private')>Private</option>
            </select>
        </div>

        <button class="btn-primary" type="submit">Create organization</button>
    </form>
@endsection
