<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Services\Media\InvalidMediaFileException;
use App\Services\Media\MediaStorageService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation, MediaStorageService $storage, NotificationService $notifications, \App\Services\UserConnectionService $connections): RedirectResponse
    {
        $this->authorize('send', $conversation);

        // Phase 23: a block also stops NEW messages into an already-existing
        // conversation — old messages stay fully intact and readable, only
        // further sending is blocked.
        if ($other = $conversation->otherParticipant($request->user())?->user) {
            if (! $connections->canInteract($request->user(), $other)) {
                return back()->withErrors(['body' => 'You cannot message this user.']);
            }
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:'.config('media.max_size_kb.document')],
        ]);

        if (empty($data['body']) && ! $request->hasFile('attachment')) {
            return back()->withErrors(['body' => 'A message needs text or an attachment.']);
        }

        $mediaId = null;
        $type = 'text';

        if ($request->hasFile('attachment')) {
            try {
                // Message attachments are always private — they are never made
                // public simply because a message exists, regardless of who the
                // sender or recipient is.
                $media = $storage->store($request->file('attachment'), ['user_id' => $request->user()->id], 'private');
            } catch (InvalidMediaFileException $e) {
                return back()->withErrors(['attachment' => $e->getMessage()]);
            }

            $mediaId = $media->id;
            $type = 'file';
        }

        $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'type' => $type,
            'body' => $data['body'] ?? null,
            'media_id' => $mediaId,
        ]);

        $conversation->touch();

        if ($other = $conversation->otherParticipant($request->user())?->user) {
            $notifications->notify(
                recipient: $other,
                type: 'message.new',
                title: $request->user()->name.' sent you a message',
                actor: $request->user(),
                relatedType: Notification::RELATED_CONVERSATION,
                relatedId: $conversation->id,
            );
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function update(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        $this->assertBelongsToConversation($conversation, $message);
        $this->authorize('update', $message);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return redirect()->route('messages.show', $conversation);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): RedirectResponse
    {
        $this->assertBelongsToConversation($conversation, $message);
        $this->authorize('delete', $message);

        $message->delete();

        return redirect()->route('messages.show', $conversation);
    }

    private function assertBelongsToConversation(Conversation $conversation, Message $message): void
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
    }
}
