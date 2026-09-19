<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('profile.show', [
            'profile' => $request->user()->profile,
            'preference' => $request->user()->preferences,
        ]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'profile' => $request->user()->profile,
            'preference' => $request->user()->preferences,
            'languages' => Language::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(StoreUserProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $language = $data['language'] ?? null;
        unset($data['language']);

        $request->user()->profile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            array_merge(['user_id' => $request->user()->id], $data)
        );

        if ($language) {
            $request->user()->preferences()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['language' => $language]
            );
        }

        return redirect()->route('profile.show')->with('status', 'Profile updated.');
    }
}
