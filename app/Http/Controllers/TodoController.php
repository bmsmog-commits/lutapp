<?php

namespace App\Http\Controllers;

use App\Models\TodoItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TodoController extends Controller
{
    public function index(Request $request): View
    {
        return view('todos.index', [
            'todos' => $request->user()->todoItems()
                ->orderBy('is_completed')
                ->orderByRaw('due_date is null')
                ->orderBy('due_date')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'details' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,normal,high'],
        ]);

        $request->user()->todoItems()->create($data);

        return back()->with('status', 'Task added.');
    }

    public function toggle(Request $request, TodoItem $todo): RedirectResponse
    {
        $this->authorizeOwner($request, $todo);

        $todo->update(['is_completed' => ! $todo->is_completed]);

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Request $request, TodoItem $todo): RedirectResponse
    {
        $this->authorizeOwner($request, $todo);
        $todo->delete();

        return back()->with('status', 'Task deleted.');
    }

    private function authorizeOwner(Request $request, TodoItem $todo): void
    {
        abort_unless($todo->user_id === $request->user()->id, 404);
    }
}
