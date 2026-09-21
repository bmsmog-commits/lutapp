<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $user = $request->user();

        // Original personal-workspace widgets (Notes/Todos/personal
        // Events/Hymns) stay exactly as they were — the dashboard is
        // additive, not a replacement.
        return view('dashboard', array_merge([
            'notesCount' => $user->notes()->count(),
            'openTasksCount' => $user->todoItems()->where('is_completed', false)->count(),
            'upcomingEvents' => $user->events()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get(),
            'hymnsCount' => $user->hymns()->count(),
        ], $dashboard->forUser($user)));
    }
}
