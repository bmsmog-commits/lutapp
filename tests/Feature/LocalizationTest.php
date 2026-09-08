<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_visitors_are_sent_to_language_selection(): void
    {
        $this->get('/')->assertRedirect(route('language.select'));
    }

    public function test_language_selection_persists_for_guest_visitors(): void
    {
        $this->post(route('language.update'), ['locale' => 'yo'])
            ->assertRedirect(route('login'))
            ->assertCookie('lutapp_locale', 'yo');

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_locale_middleware_applies_the_selected_language(): void
    {
        $this->withSession(['locale' => 'fr'])->get(route('login'))->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }
}
