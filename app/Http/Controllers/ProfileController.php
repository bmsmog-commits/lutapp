<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserProfileRequest;
use App\Models\Language;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
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

    public function storePhoto(Request $request, MediaStorageService $storage): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:'.config('media.max_size_kb.image')],
        ]);

        $profile = $request->user()->profile;

        if (! $profile) {
            return back()->withErrors(['photo' => 'Please complete your profile (choose a username) before uploading a photo.']);
        }

        $oldPhoto = $profile->profilePhoto;

        try {
            // Personal profile photos stay private by default — visible only to the
            // owner through the authorized files.show route, per Rule 18: nothing
            // here creates a public user directory or exposes photos in member lists.
            $newPhoto = $storage->store($request->file('photo'), ['user_id' => $request->user()->id], 'private');
        } catch (InvalidMediaFileException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        // Only swap the reference — and only remove the old file — after the new
        // one has been fully validated and stored, so a failed upload never
        // touches the existing photo (Rule 7).
        $profile->update(['profile_photo_media_id' => $newPhoto->id]);

        if ($oldPhoto) {
            $storage->delete($oldPhoto);
        }

        return redirect()->route('profile.show')->with('status', 'Profile photo updated.');
    }

    public function destroyPhoto(Request $request, MediaStorageService $storage): RedirectResponse
    {
        $profile = $request->user()->profile;
        $photo = $profile?->profilePhoto;

        if ($photo) {
            $profile->update(['profile_photo_media_id' => null]);
            $storage->delete($photo);
        }

        return redirect()->route('profile.show')->with('status', 'Profile photo removed.');
    }
}
