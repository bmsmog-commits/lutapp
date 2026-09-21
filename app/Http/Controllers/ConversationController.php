<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConversationController extends Controller
{
    private const SEARCH_RESULT_LIMIT = 10;
    private const MESSAGES_PER_PAGE = 30;

    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user)
            ->with(['participants.user.profile', 'latestMessage.sender'])
            ->withAggregate('latestMessage as latest_message_at', 'created_at')
            ->orderByDesc('latest_message_at')
            ->paginate(20);

        $conversationIds = $conversations->getCollection()->pluck('id');

        // A single grouped query for all rows on the page — comparing each
        // message's created_at against that specific participant's own
        // last_read_at requires the join (a plain WHERE can't express a
        // per-row threshold), but it is still one query rather than one per
        // conversation.
        $unreadCounts = DB::table('messages')
            ->join('conversation_participants', function ($join) use ($user) {
                $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_participants.user_id', $user->id);
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('messages.sender_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->where(function ($q) {
                $q->whereNull('conversation_participants.last_read_at')
                    ->orWhereColumn('messages.created_at', '>', 'conversation_participants.last_read_at');
            })
            ->groupBy('messages.conversation_id')
            ->pluck(DB::raw('count(*) as aggregate'), 'messages.conversation_id');

        $conversations->getCollection()->transform(function (Conversation $conversation) use ($user, $unreadCounts) {
            $conversation->other_participant = $conversation->otherParticipant($user);
            $conversation->unread_count = (int) ($unreadCounts[$conversation->id] ?? 0);

            return $conversation;
        });

        return view('messages.index', ['conversations' => $conversations]);
    }

    public function search(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $results = collect();

        if (mb_strlen($query) >= 2 && mb_strlen($query) <= 100) {
            $results = User::query()
                ->where('id', '!=', $request->user()->id)
                ->where(function ($q) use ($query) {
                    // An exact email match is a direct lookup ("message this
                    // specific address"), not discovery — it stays available
                    // even to a user who opted out of appearing in fuzzy
                    // name/username search, same as most messaging apps let
                    // you add a known contact by their exact address.
                    $q->where('email', $query)
                        ->orWhere(function ($fuzzy) use ($query) {
                            $fuzzy->where(function ($match) use ($query) {
                                $match->where('name', 'like', '%'.$query.'%')
                                    ->orWhereHas('profile', function ($profileQuery) use ($query) {
                                        $profileQuery->where('username', 'like', '%'.$query.'%')
                                            ->orWhere('display_name', 'like', '%'.$query.'%');
                                    });
                            })
                            // Phase 21 privacy preference — see UserSearchProvider.
                            ->where(function ($discoverable) {
                                $discoverable->whereDoesntHave('preferences')
                                    ->orWhereHas('preferences', function ($p) {
                                        $p->whereNull('privacy_preferences')
                                            ->orWhereJsonDoesntContain('privacy_preferences->discoverable', false);
                                    });
                            });
                        });
                })
                ->with('profile')
                ->limit(self::SEARCH_RESULT_LIMIT)
                ->get();
        }

        return view('messages.search', ['query' => $query, 'results' => $results]);
    }

    public function start(Request $request, \App\Services\UserConnectionService $connections): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((int) $data['user_id'] === $request->user()->id) {
            return back()->withErrors(['user_id' => 'You cannot start a conversation with yourself.']);
        }

        $other = User::findOrFail($data['user_id']);

        // Phase 23: a block prevents starting a NEW conversation in either
        // direction — it does not touch any conversation/message that
        // already exists (see MessageController::store for the same rule
        // applied to sending into an existing one).
        if (! $connections->canInteract($request->user(), $other)) {
            return back()->withErrors(['user_id' => 'You cannot start a conversation with this user.']);
        }

        $conversation = Conversation::findOrCreateDirect($request->user(), $other);

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);

        $user = $request->user();

        $messages = $conversation->messages()
            ->with(['sender.profile', 'attachment'])
            ->latest('created_at')
            ->paginate(self::MESSAGES_PER_PAGE);

        // Reverse the page's collection only (not the query) so the newest
        // page still loads via an indexed ORDER BY created_at DESC, while the
        // chat still reads oldest-to-newest top-to-bottom.
        $messages->setCollection($messages->getCollection()->reverse()->values());

        // Opening the conversation is itself the "mark read" action — only on
        // the first page, so paging back through older history doesn't move
        // the watermark past newer messages the user hasn't actually seen yet.
        if ($messages->currentPage() === 1) {
            $conversation->participantFor($user)?->update(['last_read_at' => now()]);
        }

        return view('messages.show', [
            'conversation' => $conversation->load(['participants.user.profile']),
            'otherParticipant' => $conversation->otherParticipant($user),
            'messages' => $messages,
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);

        $conversation->participantFor($request->user())?->update(['last_read_at' => now()]);

        return back();
    }
}
