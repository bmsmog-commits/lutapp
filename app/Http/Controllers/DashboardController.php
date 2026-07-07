<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'notesCount' => $user->notes()->count(),
            'openTasksCount' => $user->todoItems()->where('is_completed', false)->count(),
            'upcomingEvents' => $user->events()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'hymnsCount' => $user->hymns()->count(),
        ]);
    }
}
