@extends('layouts.app')

@push('styles')
<style>
    .workspace {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 18px;
    }
    .panel, .task {
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #fff;
        padding: 16px;
    }
    .stack { display: grid; gap: 12px; }
    .task {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    .task.done { opacity: .62; }
    .task.done strong { text-decoration: line-through; }
    @media (max-width: 800px) { .workspace { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <h1>To-do list</h1>
    <div class="workspace">
        <section class="panel">
            <form class="stack" method="post" action="{{ route('todos.store') }}">
                @csrf
                <input class="input" name="title" placeholder="Task title" required>
                <textarea name="details" placeholder="Details"></textarea>
                <input class="input" name="due_date" type="date">
                <select class="input" name="priority">
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
                <button class="btn-primary" type="submit">Add task</button>
            </form>
        </section>
        <section>
            @forelse ($todos as $todo)
                <article class="task {{ $todo->is_completed ? 'done' : '' }}">
                    <div>
                        <strong>{{ $todo->title }}</strong>
                        <div class="muted">
                            {{ ucfirst($todo->priority) }}
                            {{ $todo->due_date ? ' - due '.$todo->due_date->format('M j, Y') : '' }}
                        </div>
                        @if ($todo->details)
                            <p>{{ $todo->details }}</p>
                        @endif
                    </div>
                    <div class="note-actions">
                        <form method="post" action="{{ route('todos.toggle', $todo) }}">
                            @csrf
                            @method('patch')
                            <button type="submit">{{ $todo->is_completed ? 'Reopen' : 'Done' }}</button>
                        </form>
                        <form method="post" action="{{ route('todos.destroy', $todo) }}">
                            @csrf
                            @method('delete')
                            <button class="btn-danger" type="submit">Delete</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="muted">No tasks yet.</p>
            @endforelse
        </section>
    </div>
@endsection
