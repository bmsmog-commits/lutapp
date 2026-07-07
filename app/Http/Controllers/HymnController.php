<?php

namespace App\Http\Controllers;

use App\Models\Hymn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HymnController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->hymns()
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q');

                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('hymn_number', 'like', "%{$search}%")
                        ->orWhere('lyrics', 'like', "%{$search}%");
                });
            })
            ->orderBy('title');

        return view('hymns.index', [
            'hymns' => $query->get(),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'hymn_number' => ['nullable', 'string', 'max:50'],
            'author' => ['nullable', 'string', 'max:180'],
            'lyrics' => ['required', 'string', 'max:30000'],
        ]);

        $request->user()->hymns()->create($data);

        return back()->with('status', 'Hymn added.');
    }

    public function destroy(Request $request, Hymn $hymn): RedirectResponse
    {
        abort_unless($hymn->user_id === $request->user()->id, 404);

        $hymn->delete();

        return back()->with('status', 'Hymn deleted.');
    }
}
