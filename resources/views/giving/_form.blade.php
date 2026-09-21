@php $c = $campaign ?? null; @endphp

<div class="field">
    <label for="title">Title</label>
    <input class="input" type="text" id="title" name="title" value="{{ old('title', $c?->title) }}" required>
</div>

<div class="field">
    <label for="description">Description</label>
    <textarea id="description" name="description">{{ old('description', $c?->description) }}</textarea>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="target_amount">Target amount (optional)</label>
        <input class="input" type="number" step="0.01" id="target_amount" name="target_amount" value="{{ old('target_amount', $c?->target_amount !== null ? $c->targetAmountDisplay() : '') }}">
    </div>
    <div style="flex:1;">
        <label for="currency">Currency</label>
        <select class="input" id="currency" name="currency" required @if($c) disabled @endif>
            @foreach ($currencies as $currency)
                <option value="{{ $currency }}" @selected(old('currency', $c?->currency ?? 'NGN') === $currency)>{{ $currency }}</option>
            @endforeach
        </select>
        @if ($c)
            <input type="hidden" name="currency" value="{{ $c->currency }}">
        @endif
    </div>
</div>

<div class="field" style="display:flex;gap:8px;">
    <div style="flex:1;">
        <label for="starts_at">Start date</label>
        <input class="input" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', $c?->starts_at?->format('Y-m-d')) }}">
    </div>
    <div style="flex:1;">
        <label for="ends_at">End date</label>
        <input class="input" type="date" id="ends_at" name="ends_at" value="{{ old('ends_at', $c?->ends_at?->format('Y-m-d')) }}">
    </div>
</div>

<div class="field">
    <label for="visibility">Visibility</label>
    <select class="input" id="visibility" name="visibility" required>
        <option value="private" @selected(old('visibility', $c?->visibility ?? 'private') === 'private')>Private (organization members only)</option>
        <option value="public" @selected(old('visibility', $c?->visibility) === 'public')>Public</option>
    </select>
</div>
