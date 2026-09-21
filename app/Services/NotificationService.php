<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;

/**
 * The single, shared path by which every feature module creates a
 * notification — no controller creates App\Models\Notification rows
 * directly. Mirrors the existing convention in this codebase (direct
 * service calls from controllers, e.g. MediaStorageService,
 * PaymentVerificationService) rather than introducing Laravel
 * Events/Listeners, since nothing else in the app uses that pattern.
 */
class NotificationService
{
    public function notify(
        User $recipient,
        string $type,
        string $title,
        ?string $body = null,
        ?User $actor = null,
        ?Organization $organization = null,
        ?string $relatedType = null,
        ?int $relatedId = null,
    ): ?Notification {
        // Never notify someone about their own action.
        if ($actor && $actor->is($recipient)) {
            return null;
        }

        if (! $this->categoryEnabled($recipient, $type)) {
            return null;
        }

        return Notification::create([
            'user_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'organization_id' => $organization?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    private function categoryEnabled(User $user, string $type): bool
    {
        $preferences = $user->preferences;

        if ($preferences && ! $preferences->notifications_enabled) {
            return false;
        }

        $category = Notification::categoryFor($type);
        $categoryPreferences = $preferences?->notification_preferences ?? [];

        // Default to enabled — an absent key means the user has never
        // opted out of that category, not that it's silently off.
        return $categoryPreferences[$category] ?? true;
    }
}
