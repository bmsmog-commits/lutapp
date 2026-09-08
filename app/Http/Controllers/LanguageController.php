<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function landing(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->has('locale') || $request->hasCookie('lutapp_locale')) {
            return redirect()->route('login');
        }

        return redirect()->route('language.select');
    }

    public function show(Request $request): View
    {
        return view('language.select', [
            'locales' => config('app.supported_locales', []),
            'selectedLocale' => $request->session()->get(
                'locale',
                $request->cookie('lutapp_locale', config('app.locale', 'en'))
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales', [])))],
        ]);

        $locale = $data['locale'];
        $request->session()->put('locale', $locale);

        if (Auth::check()) {
            Auth::user()->preferences()->updateOrCreate(
                [],
                ['language' => $locale]
            );
        }

        $destination = Auth::check() ? 'dashboard' : 'login';

        return redirect()
            ->route($destination)
            ->withCookie(cookie('lutapp_locale', $locale, 60 * 24 * 365));
    }
}
