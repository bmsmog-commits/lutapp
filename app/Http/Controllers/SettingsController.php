<?php

namespace App\Http\Controllers;

use App\Models\BibleTranslation;
use App\Models\Notification;
use App\Models\SecurityQuestion;
use App\Models\User;
use App\Services\PreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * The one settings center for every Phase 21 preference domain. Ownership of
 * every value here is always derived from $request->user() — no route ever
 * takes a {user} parameter, so there is no ID to manipulate to reach another
 * user's settings.
 */
class SettingsController extends Controller
{
    public function index(Request $request, PreferenceService $preferences): View
    {
        $user = $request->user();

        return view('settings.index', [
            'user' => $user,
            'preference' => $user->preferences,
            'notificationCategories' => array_unique(array_values(Notification::TYPES)),
            'translations' => BibleTranslation::where('is_active', true)->orderBy('name')->get(),
            'bible' => $preferences->bible($user),
            'audio' => $preferences->audio($user),
            'theme' => $preferences->theme($user),
            'isDiscoverable' => $preferences->isDiscoverable($user),
            'dashboardSections' => $preferences->dashboardSections($user),
            'allDashboardSections' => PreferenceService::DASHBOARD_SECTIONS,
            'securityQuestion' => $user->securityQuestions()->latest()->first(),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = $data['email'] !== $user->email;

        $user->fill($data);

        // Changing the address invalidates the existing verification — the
        // same MustVerifyEmail contract the app already enforces at
        // registration, not a new verification mechanism.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            return redirect()->route('verification.notice')->with('status', 'Email updated — please verify your new address.');
        }

        return redirect()->route('settings.index')->with('status', 'Account updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->update(['password' => $data['password']]);

        return redirect()->route('settings.index')->with('status', 'Password updated.');
    }

    public function updateSecurityQuestion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'security_question' => ['required', 'string', 'max:255'],
            'security_answer' => ['required', 'string', 'min:2'],
        ]);

        // Same hashing convention as registration (AuthController::register)
        // — one security question per user, updated in place.
        $request->user()->securityQuestions()->delete();
        SecurityQuestion::create([
            'user_id' => $request->user()->id,
            'question' => $data['security_question'],
            'answer_hash' => Hash::make(strtolower(trim($data['security_answer']))),
        ]);

        return redirect()->route('settings.index')->with('status', 'Security question updated.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        $categories = array_unique(array_values(Notification::TYPES));

        $data = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => [Rule::in($categories)],
        ]);

        $enabledCategories = $data['categories'] ?? [];
        $preferences = collect($categories)->mapWithKeys(fn ($category) => [$category => in_array($category, $enabledCategories, true)])->all();

        $user->preferences()->updateOrCreate([], [
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'notification_preferences' => $preferences,
        ]);

        return redirect()->route('settings.index')->with('status', 'Notification preferences updated.');
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'discoverable' => ['nullable', 'boolean'],
        ]);

        $user->preferences()->updateOrCreate([], [
            'privacy_preferences' => ['discoverable' => $request->boolean('discoverable')],
        ]);

        return redirect()->route('settings.index')->with('status', 'Privacy preferences updated.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(PreferenceService::THEMES)],
        ]);

        $request->user()->preferences()->updateOrCreate([], ['theme' => $data['theme']]);

        return redirect()->route('settings.index')->with('status', 'Appearance updated.');
    }

    public function updateBible(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'translation_id' => ['nullable', 'exists:bible_translations,id'],
            'font_size' => ['required', Rule::in(PreferenceService::BIBLE_FONT_SIZES)],
        ]);

        $request->user()->preferences()->updateOrCreate([], [
            'bible_preferences' => [
                'translation_id' => $data['translation_id'] ?? null,
                'font_size' => $data['font_size'],
            ],
        ]);

        return redirect()->route('settings.index')->with('status', 'Bible preferences updated.');
    }

    public function updateAudio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'autoplay' => ['nullable', 'boolean'],
        ]);

        $request->user()->preferences()->updateOrCreate([], [
            'audio_preferences' => ['autoplay' => $request->boolean('autoplay')],
        ]);

        return redirect()->route('settings.index')->with('status', 'Audio preferences updated.');
    }

    public function updateDashboard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*' => [Rule::in(PreferenceService::DASHBOARD_SECTIONS)],
        ]);

        $request->user()->preferences()->updateOrCreate([], [
            'dashboard_preferences' => ['sections' => $data['sections'] ?? []],
        ]);

        return redirect()->route('settings.index')->with('status', 'Dashboard preferences updated.');
    }
}
