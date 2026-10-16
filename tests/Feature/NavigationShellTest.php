<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 27.5 — the floating navigation shell must keep every destination the
 * old horizontal top nav offered. This guards against a redesign silently
 * dropping a route from the UI.
 */
class NavigationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_panel_exposes_every_destination(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        foreach ([
            'dashboard', 'search.index', 'notes.index', 'todos.index', 'events.index',
            'hymns.index', 'bible.index', 'bible.bookmarks', 'bible.reading-history',
            'bible.search', 'organizations.index', 'directory.index', 'messages.index',
            'resources.index', 'jobs.index', 'org-events.discover', 'audio.index',
            'giving.mine', 'notifications.index', 'profile.show', 'settings.index',
            'language.select',
        ] as $routeName) {
            $response->assertSee(route($routeName), false);
        }

        $response->assertSee(route('logout'), false);
    }

    public function test_floating_navigation_button_is_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('aria-label="Open navigation menu"', false);
        $response->assertSee('aria-controls="nav-drawer"', false);
        $response->assertSee('aria-expanded="false"', false);
    }

    /**
     * Phase 27.7 — the two floating controls must read as a hierarchy, not as
     * two identical circles. The primary is the gold nav button; the secondary
     * is a labelled "Lutapp AI" pill stacked above it.
     */
    public function test_the_floating_controls_are_a_primary_and_a_labelled_secondary(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('id="nav-fab"', false);
        $response->assertSee('id="ai-fab"', false);
        // The secondary control carries a label, so it cannot be mistaken for
        // a second navigation button.
        $response->assertSee('ai-fab-label', false);
        $response->assertSee('Lutapp AI');
    }

    public function test_the_footer_carries_the_application_credit_lines(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Created by BMS — Beyond Microsoft')
            ->assertSee('© 2026 Lutapp. All Rights Reserved.');
    }

    public function test_active_route_is_marked_as_current_in_the_navigation_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notes.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false);
    }

    /**
     * Phase 27.7 — "Connections" is surfaced in the Community group. It maps to
     * the app's EXISTING users.followers route, which needs the viewer's own
     * profile username, so it appears only for a user who has a profile. No new
     * route was invented for it.
     */
    public function test_connections_is_present_in_the_navigation_for_a_user_with_a_profile(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'username' => 'navconnections']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Connections')
            ->assertSee(route('users.followers', 'navconnections'), false);
    }

    public function test_navigation_still_renders_when_the_user_has_no_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="nav-drawer"', false);
    }

    public function test_guests_do_not_receive_the_navigation_panel(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('id="nav-drawer"', false);
    }
}
