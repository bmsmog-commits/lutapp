<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->notifications();

        if ($request->query('filter') === 'unread') {
            $query->unread();
        }

        return view('notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $request->query('filter', 'all'),
            'unreadCount' => $request->user()->unreadNotificationsCount(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        $destination = $notification->relatedUrl($request->user());

        return $destination ? redirect()->away($destination) : back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->notifications()->unread()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
