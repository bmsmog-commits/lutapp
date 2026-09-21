@php $c = $collection ?? null; @endphp

<div class="field">
    <label for="title">Title</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $c?->title) }}" required>
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $c?->description) }}</textarea>
</div>

<div class="field">
    <label for="type">Type</label>
    <select class="input" id="type" name="type" required>
        @foreach ($types as $type)
            <option value="{{ $type }}" @selected(old('type', $c?->type ?? 'other') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="visibility">Visibility</label>
    <select class="input" id="visibility" name="visibility" required>
        <option value="private" @selected(old('visibility', $c?->visibility ?? 'private') === 'private')>Private</option>
        <option value="public" @selected(old('visibility', $c?->visibility) === 'public')>Public</option>
    </select>
</div>
