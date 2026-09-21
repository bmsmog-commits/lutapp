<?php

namespace App\Services;

use App\Models\User;

/**
 * The single typed accessor for every Phase 21 preference domain — Bible,
 * Audio, Dashboard, Privacy, Appearance all read through here, never a raw
 * `$user->preferences->some_json_field['key'] ?? ...` scattered across
 * controllers/views. Every getter has a safe default that matches the
 * application's existing pre-Phase-21 behavior, so a user with no saved
 * preferences (the common case for every existing account) sees nothing
 * change.
 *
 * Notification preferences are deliberately NOT duplicated here —
 * NotificationService already reads user_preferences.notifications_enabled /
 * notification_preferences directly (Phase 18), and that remains the single
 * source of truth for notification delivery. This service only adds the
 * settings-UI layer on top of that same, already-working column.
 */
class PreferenceService
{
    public const DASHBOARD_SECTIONS = [
        'notifications', 'conversations', 'organizations', 'upcomingOrgEvents',
        'jobs', 'resources', 'audio', 'bible', 'giving', 'connections',
    ];

    public const THEMES = ['system', 'light', 'dark'];

    public const BIBLE_FONT_SIZES = ['small', 'medium', 'large'];

    public function theme(User $user): string
    {
        $theme = $user->preferences?->theme;

        return in_array($theme, self::THEMES, true) ? $theme : 'system';
    }

    // The one genuinely enforceable privacy preference found in this app
    // (see Phase 21 audit): whether the user is findable through Phase 19's
    // people-search and Phase 12's message-recipient search. Every other
    // candidate (email/phone/location visibility) has no existing exposure
    // path to begin with — nothing in the app shows another user's email,
    // phone, or address anywhere — so no decorative toggle was added for them.
    public function isDiscoverable(User $user): bool
    {
        return (bool) ($user->preferences?->privacy_preferences['discoverable'] ?? true);
    }

    // Phase 22 reuses this exact same preference as the public-profile-page
    // gate — deliberately not a second "profile visibility" flag. A user
    // always sees their own profile page (useful as a live preview of what
    // others would see); everyone else is subject to the discoverable check.
    public function canViewPublicProfile(User $profileUser, ?User $viewer): bool
    {
        if ($viewer && $viewer->is($profileUser)) {
            return true;
        }

        // Phase 24: a suspended/deactivated account is hidden from everyone
        // but itself, on top of (not instead of) the existing discoverable
        // preference — moderation is a stricter ceiling, never a looser one.
        if ($profileUser->isAccountHidden()) {
            return false;
        }

        return $this->isDiscoverable($profileUser);
    }

    public function bible(User $user): array
    {
        $preferences = $user->preferences?->bible_preferences ?? [];

        return [
            'translation_id' => $preferences['translation_id'] ?? null,
            'font_size' => in_array($preferences['font_size'] ?? null, self::BIBLE_FONT_SIZES, true)
                ? $preferences['font_size']
                : 'medium',
        ];
    }

    public function audio(User $user): array
    {
        $preferences = $user->preferences?->audio_preferences ?? [];

        return [
            'autoplay' => (bool) ($preferences['autoplay'] ?? false),
        ];
    }

    // Every section, in DashboardService's own display order — a user who
    // has never touched this setting gets every widget, identical to
    // pre-Phase-21 dashboard behavior.
    public function dashboardSections(User $user): array
    {
        $preferences = $user->preferences?->dashboard_preferences ?? [];
        $enabled = $preferences['sections'] ?? null;

        if (! is_array($enabled)) {
            return self::DASHBOARD_SECTIONS;
        }

        return array_values(array_intersect(self::DASHBOARD_SECTIONS, $enabled));
    }
}
