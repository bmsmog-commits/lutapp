@php $j = $job ?? null; @endphp

<div class="field">
    <label for="title">Title</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $j?->title) }}" required>
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $j?->description) }}</textarea>
</div>

<div class="field">
    <label for="category">Category</label>
    <select class="input" id="category" name="category">
        <option value="">Select a category</option>
        @foreach ($categories as $category)
            <option value="{{ $category }}" @selected(old('category', $j?->category) === $category)>{{ $category }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="work_mode">Work mode</label>
    <select class="input" id="work_mode" name="work_mode" required>
        @foreach ($workModes as $mode)
            <option value="{{ $mode }}" @selected(old('work_mode', $j?->work_mode ?? 'remote') === $mode)>{{ ucfirst(str_replace('_', ' ', $mode)) }}</option>
        @endforeach
    </select>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="country">Country</label>
        <input class="input" type="text" id="country" name="country" value="{{ old('country', $j?->country) }}">
    </div>
    <div style="flex:1;">
        <label for="state">State/Region</label>
        <input class="input" type="text" id="state" name="state" value="{{ old('state', $j?->state) }}">
    </div>
    <div style="flex:1;">
        <label for="city">City</label>
        <input class="input" type="text" id="city" name="city" value="{{ old('city', $j?->city) }}">
    </div>
</div>

<div class="field">
    <label for="budget_type">Budget</label>
    <select class="input" id="budget_type" name="budget_type">
        <option value="">Not specified</option>
        @foreach ($budgetTypes as $type)
            <option value="{{ $type }}" @selected(old('budget_type', $j?->budget_type) === $type)>{{ ucfirst($type) }}</option>
        @endforeach
    </select>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="budget_min">Min amount</label>
        <input class="input" type="number" step="0.01" id="budget_min" name="budget_min" value="{{ old('budget_min', $j?->budget_min) }}">
    </div>
    <div style="flex:1;">
        <label for="budget_max">Max amount</label>
        <input class="input" type="number" step="0.01" id="budget_max" name="budget_max" value="{{ old('budget_max', $j?->budget_max) }}">
    </div>
    <div style="flex:1;">
        <label for="currency">Currency</label>
        <input class="input" type="text" id="currency" name="currency" value="{{ old('currency', $j?->currency) }}" placeholder="NGN">
    </div>
</div>

<div class="field">
    <label for="application_deadline">Application deadline</label>
    <input class="input" type="date" id="application_deadline" name="application_deadline" value="{{ old('application_deadline', $j?->application_deadline?->format('Y-m-d')) }}">
</div>

<div class="field">
    <label for="visibility">Visibility</label>
    <select class="input" id="visibility" name="visibility" required>
        <option value="private" @selected(old('visibility', $j?->visibility ?? 'private') === 'private')>Private</option>
        <option value="public" @selected(old('visibility', $j?->visibility) === 'public')>Public</option>
    </select>
</div>
