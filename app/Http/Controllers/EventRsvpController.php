<?php

namespace App\Http\Controllers;

use App\Models\EventFullException;
use App\Models\Notification;
use App\Models\OrganizationEvent;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventRsvpController extends Controller
{
    public function store(Request $request, OrganizationEvent $event, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('view', $event);

        if (! $event->acceptsRsvps()) {
            return back()->withErrors(['rsvp' => 'This event is not currently accepting RSVPs.']);
        }

        // Only a genuine new-to-attending transition is meaningful activity —
        // an already-attending user hitting RSVP again must not spam the
        // organizer with a duplicate notification.
        $wasAlreadyAttending = $event->rsvps()->where('user_id', $request->user()->id)->where('status', 'attending')->exists();

        try {
            $event->rsvp($request->user());
        } catch (EventFullException $e) {
            return back()->withErrors(['rsvp' => $e->getMessage()]);
        }

        if (! $wasAlreadyAttending && $event->creator) {
            $notifications->notify(
                recipient: $event->creator,
                type: 'event.rsvp.new',
                title: $request->user()->name.' RSVP\'d to "'.$event->title.'"',
                actor: $request->user(),
                organization: $event->organization,
                relatedType: Notification::RELATED_ORGANIZATION_EVENT,
                relatedId: $event->id,
            );
        }

        return redirect()->route('org-events.show', $event)->with('status', 'You are attending this event.');
    }

    public function destroy(Request $request, OrganizationEvent $event): RedirectResponse
    {
        $rsvp = $event->rsvps()->where('user_id', $request->user()->id)->firstOrFail();

        $this->authorize('cancel', $rsvp);

        $rsvp->update(['status' => 'cancelled']);

        return redirect()->route('org-events.show', $event)->with('status', 'RSVP cancelled.');
    }
}
