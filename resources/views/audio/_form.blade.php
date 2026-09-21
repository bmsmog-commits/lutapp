@php $a = $audio ?? null; @endphp

<div class="field">
    <label for="title">Title</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $a?->title) }}" required>
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $a?->description) }}</textarea>
</div>

<div class="field">
    <label for="creator_name">Creator/Artist name</label>
    <input class="input" type="text" id="creator_name" name="creator_name" value="{{ old('creator_name', $a?->creator_name) }}">
</div>

<div class="field">
    <label for="category">Category</label>
    <select class="input" id="category" name="category">
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category }}" @selected(old('category', $a?->category) === $category)>{{ $category }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="language_id">Language</label>
    <select class="input" id="language_id" name="language_id">
        <option value="">Select a language</option>
        @foreach ($languages as $language)
            <option value="{{ $language->id }}" @selected((string) old('language_id', $a?->language_id) === (string) $language->id)>{{ $language->name }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="released_on">Release date</label>
    <input class="input" type="date" id="released_on" name="released_on" value="{{ old('released_on', $a?->released_on?->format('Y-m-d')) }}">
</div>

<div class="field">
    <label for="visibility">Visibility</label>
    <select class="input" id="visibility" name="visibility" required>
        <option value="private" @selected(old('visibility', $a?->visibility ?? 'private') === 'private')>Private</option>
        <option value="public" @selected(old('visibility', $a?->visibility) === 'public')>Public</option>
    </select>
</div>
