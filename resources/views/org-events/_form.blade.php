@php $e = $event ?? null; @endphp

<div class="field">
    <label for="title">Title</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $e?->title) }}" required>
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $e?->description) }}</textarea>
</div>

<div class="field">
    <label for="category">Category</label>
    <select class="input" id="category" name="category">
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category }}" @selected(old('category', $e?->category) === $category)>{{ $category }}</option>
        @endforeach
    </select>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="starts_at">Starts at</label>
        <input class="input" type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', $e?->starts_at?->format('Y-m-d\TH:i')) }}" required>
    </div>
    <div style="flex:1;">
        <label for="ends_at">Ends at</label>
        <input class="input" type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $e?->ends_at?->format('Y-m-d\TH:i')) }}">
    </div>
    <div style="flex:1;">
        <label for="timezone">Timezone</label>
        <input class="input" type="text" id="timezone" name="timezone" value="{{ old('timezone', $e?->timezone) }}" placeholder="Africa/Lagos">
    </div>
</div>

<div class="field">
    <label for="location_mode">Location mode</label>
    <select class="input" id="location_mode" name="location_mode" required>
        @foreach ($locationModes as $mode)
            <option value="{{ $mode }}" @selected(old('location_mode', $e?->location_mode ?? 'physical') === $mode)>{{ ucfirst($mode) }}</option>
        @endforeach
    </select>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="country">Country</label>
        <input class="input" type="text" id="country" name="country" value="{{ old('country', $e?->country) }}">
    </div>
    <div style="flex:1;">
        <label for="state">State/Region</label>
        <input class="input" type="text" id="state" name="state" value="{{ old('state', $e?->state) }}">
    </div>
    <div style="flex:1;">
        <label for="city">City</label>
        <input class="input" type="text" id="city" name="city" value="{{ old('city', $e?->city) }}">
    </div>
</div>

<div class="field">
    <label for="address">Address</label>
    <input class="input" type="text" id="address" name="address" value="{{ old('address', $e?->address) }}">
</div>

<div class="field">
    <label for="online_url">Online meeting link (private — only shown to RSVP'd attendees and managers)</label>
    <input class="input" type="url" id="online_url" name="online_url" value="{{ old('online_url', $e?->online_url) }}">
</div>

<div class="field">
    <label for="capacity">Capacity (optional)</label>
    <input class="input" type="number" min="1" id="capacity" name="capacity" value="{{ old('capacity', $e?->capacity) }}">
</div>

<div class="field">
    <label for="visibility">Visibility</label>
    <select class="input" id="visibility" name="visibility" required>
        <option value="private" @selected(old('visibility', $e?->visibility ?? 'private') === 'private')>Private (organization members only)</option>
        <option value="public" @selected(old('visibility', $e?->visibility) === 'public')>Public</option>
    </select>
</div>
