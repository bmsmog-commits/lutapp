<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\SecurityQuestion;
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

    // Note Passcode Recovery
    public function showRecoveryForm(Request $request, Note $note): View
    {
        $this->authorizeNote($request, $note);

        if (! $note->isLocked()) {
            return redirect()->route('notes.index')->with('info', 'This note is not locked.');
        }

        $securityQuestion = $request->user()->securityQuestions()->first();

        if (! $securityQuestion) {
            return redirect()->route('notes.index')->with('error', 'No security questions set up. Please update your account settings.');
        }

        return view('notes.recover-passcode', [
            'note' => $note,
            'question' => $securityQuestion->question,
        ]);
    }

    public function submitRecoveryAnswer(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);

        if (! $note->isLocked()) {
            return redirect()->route('notes.index')->with('info', 'This note is not locked.');
        }

        $securityQuestion = $request->user()->securityQuestions()->first();

        if (! $securityQuestion) {
            return redirect()->route('notes.index')->with('error', 'No security questions set up.');
        }

        // Check rate limiting
        $recovery = $note->recoveryAttempts()->firstOrCreate(
            ['user_id' => $request->user()->id, 'note_id' => $note->id],
            ['attempts' => 0]
        );

        if ($recovery->locked_until && now()->lessThan($recovery->locked_until)) {
            $minutes = now()->diffInMinutes($recovery->locked_until);
            return back()->withErrors(['answer' => "Too many failed attempts. Please try again in {$minutes} minutes."]);
        }

        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2'],
        ]);

        if (! Hash::check(strtolower(trim($data['answer'])), $securityQuestion->answer_hash)) {
            $recovery->increment('attempts');
            $recovery->update(['last_attempt_at' => now()]);

            if ($recovery->attempts >= 3) {
                $recovery->update(['locked_until' => now()->addMinutes(30)]);
                return back()->withErrors(['answer' => 'Incorrect answer. Your account is locked for 30 minutes due to too many failed attempts.']);
            }

            $remaining = 3 - $recovery->attempts;

            return back()->withErrors(['answer' => "Incorrect answer. You have {$remaining} attempts remaining."]);
        }

        $recovery->update(['attempts' => 0, 'locked_until' => null]);

        $verified = $request->session()->get('note_recovery_verified', []);
        $verified[$note->id] = true;
        $request->session()->put('note_recovery_verified', $verified);

        return redirect()
            ->route('notes.reset-passcode', $note)
            ->with('status', 'Security answer verified. Set a new note passcode.');
    }

    public function showResetPasscodeForm(Request $request, Note $note): View|RedirectResponse
    {
        $this->authorizeNote($request, $note);

        if (! $note->isLocked()) {
            return redirect()->route('notes.index')->with('info', 'This note is not locked.');
        }

        if (! $this->isRecoveryVerified($request, $note)) {
            return redirect()
                ->route('notes.recover-passcode', $note)
                ->withErrors(['answer' => 'Verify your security answer before setting a new passcode.']);
        }

        return view('notes.reset-passcode', ['note' => $note]);
    }

    public function resetPasscode(Request $request, Note $note): RedirectResponse
    {
        $this->authorizeNote($request, $note);

        if (! $note->isLocked()) {
            return redirect()->route('notes.index')->with('info', 'This note is not locked.');
        }

        if (! $this->isRecoveryVerified($request, $note)) {
            return redirect()
                ->route('notes.recover-passcode', $note)
                ->withErrors(['answer' => 'Verify your security answer before setting a new passcode.']);
        }

        $data = $request->validate([
            'passcode' => ['required', 'string', 'min:4', 'max:32', 'confirmed'],
        ]);

        $note->update(['passcode_hash' => Hash::make($data['passcode'])]);

        $verified = $request->session()->get('note_recovery_verified', []);
        unset($verified[$note->id]);
        $request->session()->put('note_recovery_verified', $verified);

        $unlocked = $request->session()->get('unlocked_notes', []);
        $unlocked[$note->id] = true;
        $request->session()->put('unlocked_notes', $unlocked);

        return redirect()->route('notes.index')->with('status', 'Note passcode updated.');
    }

    private function isRecoveryVerified(Request $request, Note $note): bool
    {
        return (bool) ($request->session()->get('note_recovery_verified', [])[$note->id] ?? false);
    }
}
