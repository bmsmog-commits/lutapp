<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_email_verification_and_keeps_user_in_verification_flow(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Ada User',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'security_question' => "What is your uncle's name?",
            'security_answer' => 'Samuel',
        ]);

        $user = User::where('email', 'ada@example.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_can_reach_the_verification_notice_after_login(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('verification.notice'))->assertOk();
    }
}
