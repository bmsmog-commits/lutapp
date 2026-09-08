@extends('layouts.app')

@section('content')
<div style="max-width: 500px; margin: 40px auto; padding: 24px; background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h2 style="margin-top: 0;">Recover Note Passcode</h2>
    
    <p style="color: #5f6368; margin-bottom: 24px;">
        <strong>Note:</strong> {{ $note->title ?: 'Untitled Note' }}
    </p>

    @if ($errors->any())
        <div style="padding: 12px; background: #ffebee; border-radius: 4px; margin-bottom: 20px; color: #c62828;">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div style="padding: 16px; background: #fff3e0; border-radius: 4px; margin-bottom: 24px; border-left: 4px solid #f57c00;">
        <p style="margin: 0; color: #e65100; font-weight: 500;">
            Answer your security question correctly to set a new passcode for this note.
        </p>
    </div>

    <form method="POST" action="{{ route('notes.submit-recovery', $note) }}">
        @csrf

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 12px; font-weight: 500; color: #5f6368;">
                Security Question
            </label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #fbbc04;">
                <strong>{{ $question }}</strong>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <label for="answer" style="display: block; margin-bottom: 8px; font-weight: 500;">Your Answer</label>
            <input 
                type="text" 
                id="answer" 
                name="answer" 
                required 
                autofocus
                style="width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 4px; font-size: 14px;"
                placeholder="Enter your answer"
            >
            <small style="color: #5f6368; display: block; margin-top: 8px;">
                Note: Answers are case-insensitive and leading/trailing spaces are ignored.
            </small>
        </div>

        <button type="submit" style="width: 100%; padding: 12px; background: #fbbc04; border: none; border-radius: 4px; font-weight: 500; cursor: pointer; margin-bottom: 12px;">
            Submit Answer
        </button>

        <a href="{{ route('notes.index') }}" style="display: block; text-align: center; color: #5f6368; text-decoration: none;">
            Cancel
        </a>
    </form>

    <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #dadce0; font-size: 12px; color: #5f6368;">
        <strong>Security Reminder:</strong>
        <ul style="margin: 8px 0 0 20px; padding: 0;">
            <li>You have 3 attempts to answer correctly</li>
            <li>After 3 failed attempts, your account will be locked for 30 minutes</li>
            <li>Never share your security answer with anyone</li>
        </ul>
    </div>
</div>
@endsection
