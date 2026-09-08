@extends('layouts.app')

@section('content')
    <div class="auth-shell">
        <section class="auth-card">
            <h1>Set a new note passcode</h1>
            <p class="muted">{{ $note->title ?: 'Untitled Note' }}</p>

            <form method="post" action="{{ route('notes.reset-passcode.update', $note) }}">
                @csrf
                <div class="field">
                    <label for="passcode">New passcode</label>
                    <input class="input" id="passcode" name="passcode" type="password" minlength="4" maxlength="32" required autofocus>
                </div>
                <div class="field">
                    <label for="passcode_confirmation">Confirm passcode</label>
                    <input class="input" id="passcode_confirmation" name="passcode_confirmation" type="password" minlength="4" maxlength="32" required>
                </div>
                <div class="field">
                    <button class="btn-primary" type="submit">Update passcode</button>
                    <a class="btn" href="{{ route('notes.index') }}">Cancel</a>
                </div>
            </form>
        </section>
    </div>
@endsection
