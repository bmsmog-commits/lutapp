<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class NoteController extends Controller
{
    private array $colors = [
        '#fff7b2',
        '#d7f9d2',
        '#d7ecff',
        '#ffd8d8',
        '#f2ddff',
        '#ffffff',
    ];

    public function index(Request $request): View
    {
        $query = $request->user()->notes()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q');

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_pinned')
            ->latest('updated_at');

        return view('notes.index', [
            'notes' => $query->get(),
            'colors' => $this->colors,
            'unlockedNotes' => session('unlocked_notes', []),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedNote($request);

        $request->user()->notes()->create($this->payload($data));

        return redirect()->route('notes.index')->with('status', 'Note created.');
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);

        if ($note->isLocked() && ! $this->isUnlocked($request, $note)) {
            return back()->withErrors(['passcode' => 'Unlock the note before editing it.']);
        }

        $data = $this->validatedNote($request);
        $payload = $this->payload($data, $note);

        if ($request->boolean('remove_lock')) {
            $payload['passcode_hash'] = null;
            $this->forgetUnlocked($request, $note);
        }

        $note->update($payload);

        return redirect()->route('notes.index')->with('status', 'Note updated.');
    }

    public function unlock(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);

        $data = $request->validate([
            'passcode' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        if (! $note->isLocked() || ! Hash::check($data['passcode'], $note->passcode_hash)) {
            return back()->withErrors(["unlock_{$note->id}" => 'Incorrect passcode.']);
        }

        $unlocked = session('unlocked_notes', []);
        $unlocked[$note->id] = true;
        session(['unlocked_notes' => $unlocked]);

        return redirect()->route('notes.index')->with('status', 'Note unlocked.');
    }

    public function lock(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);
        $this->forgetUnlocked($request, $note);

        return redirect()->route('notes.index')->with('status', 'Note locked.');
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);
        $this->forgetUnlocked($request, $note);
        $note->delete();

        return redirect()->route('notes.index')->with('status', 'Note deleted.');
    }

    private function validatedNote(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:10000'],
            'color' => ['required', 'string', 'in:'.implode(',', $this->colors)],
            'is_pinned' => ['nullable', 'boolean'],
            'passcode' => ['nullable', 'string', 'min:4', 'max:32'],
        ]);
    }

    private function payload(array $data, ?Note $note = null): array
    {
        $payload = [
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'color' => $data['color'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ];

        if (filled($data['passcode'] ?? null)) {
            $payload['passcode_hash'] = Hash::make($data['passcode']);
        } elseif ($note) {
            $payload['passcode_hash'] = $note->passcode_hash;
        }

        return $payload;
    }

    private function authorizeNote(Request $request, Note $note): void
    {
        abort_unless($note->user_id === $request->user()->id, 404);
    }

    private function isUnlocked(Request $request, Note $note): bool
    {
        return (bool) ($request->session()->get('unlocked_notes', [])[$note->id] ?? false);
    }

    private function forgetUnlocked(Request $request, Note $note): void
    {
        $unlocked = $request->session()->get('unlocked_notes', []);
        unset($unlocked[$note->id]);
        $request->session()->put('unlocked_notes', $unlocked);
    }
}
