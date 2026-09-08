<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = auth()->user()?->preferences?->language
            ?? $request->session()->get('locale')
            ?? $request->cookie('lutapp_locale')
            ?? config('app.locale', 'en');

        if (! array_key_exists($locale, config('app.supported_locales', []))) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
