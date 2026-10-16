<?php

namespace Tests\Feature;

use App\Models\BibleTranslation;
use App\Models\SecurityQuestion;
use App\Models\User;
use App\Services\Ai\AiAssistantInterface;
use App\Services\Ai\NullAiAssistant;
use App\Services\Messaging\NullSmsVerificationProvider;
use App\Services\Messaging\NullWhatsAppProvider;
use App\Services\Messaging\SmsVerificationProvider;
use App\Services\Messaging\WhatsAppProvider;
use App\Services\PreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 27.6 — translation availability states, identity, communication
 * preferences, the AI seam and the footer quote system.
 */
class Phase276ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- Bible

    public function test_availability_state_reports_the_richer_catalog_states(): void
    {
        $licensed = BibleTranslation::create([
            'code' => 'NLT', 'name' => 'New Living Translation', 'language' => 'English',
            'license' => 'Proprietary', 'license_type' => 'licensed_remote',
            'copyright_holder' => 'Tyndale House Foundation',
            'availability_status' => 'license_required',
            'public_domain' => false, 'redistributable' => false, 'is_active' => true,
        ]);

        $soon = BibleTranslation::create([
            'code' => 'SOON', 'name' => 'Cleared Translation', 'language' => 'English',
            'license' => 'Public Domain', 'license_type' => 'public_domain',
            'availability_status' => 'coming_soon',
            'public_domain' => true, 'redistributable' => true, 'is_active' => true,
        ]);

        $this->assertSame('license_required', $licensed->availabilityState());
        $this->assertFalse($licensed->isReadable());
        $this->assertSame('coming_soon', $soon->availabilityState());
        $this->assertFalse($soon->isReadable());
    }

    public function test_metadata_can_never_claim_availability_without_real_verse_rows(): void
    {
        $lying = BibleTranslation::create([
            'code' => 'FAKE', 'name' => 'Metadata Only', 'language' => 'English',
            'license' => 'Public Domain', 'license_type' => 'public_domain',
            'availability_status' => 'available', // deliberately mis-set
            'public_domain' => true, 'redistributable' => true, 'is_active' => true,
        ]);

        $this->assertSame(0, $lying->verses()->count());
        $this->assertNotSame('available', $lying->availabilityState());
        $this->assertFalse($lying->isReadable());
    }

    public function test_language_and_translation_are_modelled_separately(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('languages'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('bible_translations', 'language'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('bible_translations', 'language_id'));
    }

    // ------------------------------------------------------------- Identity

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada User',
            'password' => 'password',
            'password_confirmation' => 'password',
            'security_question' => "What is your uncle's name?",
            'security_answer' => 'Samuel',
        ], $overrides);
    }

    public function test_existing_email_registration_path_is_unchanged(): void
    {
        $this->post('/register', $this->registrationPayload(['email' => 'ada@example.com']))
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertNull($user->phone);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('security_questions', ['user_id' => $user->id]);
    }

    public function test_a_phone_number_can_be_added_alongside_an_email(): void
    {
        $this->post('/register', $this->registrationPayload([
            'email' => 'grace@example.com',
            'phone' => '+234 800 111 2222',
        ]))->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'grace@example.com')->firstOrFail();
        $this->assertSame('+2348001112222', $user->phone, 'The phone number is normalised before storage.');
        $this->assertNull($user->phone_verified_at);
    }

    public function test_phone_only_signup_is_refused_while_no_sms_provider_is_configured(): void
    {
        $this->assertFalse(app(SmsVerificationProvider::class)->isConfigured());

        $this->post('/register', $this->registrationPayload(['phone' => '+2348001112222']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $this->post('/register', $this->registrationPayload([
            'email' => 'x@example.com',
            'phone' => 'not-a-number',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_a_phone_number_is_unique(): void
    {
        User::factory()->create(['phone' => '+2348001112222']);

        $this->post('/register', $this->registrationPayload([
            'email' => 'new@example.com',
            'phone' => '+234 800 111 2222',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_existing_email_accounts_can_still_log_in(): void
    {
        $user = User::factory()->create(['email' => 'legacy@example.com']);

        $this->post('/login', ['email' => 'legacy@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_with_a_phone_can_log_in_with_it(): void
    {
        $user = User::factory()->create(['phone' => '+2348001112222']);

        $this->post('/login', ['email' => '+234 800 111 2222', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_blank_phone_login_never_matches_an_account_without_a_phone(): void
    {
        User::factory()->create(['phone' => null]);

        $this->post('/login', ['email' => '---', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_verification_semantics_are_unchanged_for_email_accounts(): void
    {
        $verified = User::factory()->create();
        $unverified = User::factory()->unverified()->create();

        $this->assertTrue($verified->hasVerifiedEmail());
        $this->assertFalse($unverified->hasVerifiedEmail());
    }

    // --------------------------------------------- Communication preferences

    public function test_communication_preferences_default_sensibly(): void
    {
        $user = User::factory()->create();
        $defaults = app(PreferenceService::class)->communication($user);

        $this->assertTrue($defaults['weekly_email']);
        $this->assertFalse($defaults['weekly_whatsapp']);
        $this->assertFalse($defaults['weekly_sms']);
    }

    public function test_communication_preferences_persist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('settings.communication.update'), [
            'weekly_email' => '0',
            'weekly_whatsapp' => '1',
        ])->assertRedirect(route('settings.index'));

        $prefs = app(PreferenceService::class)->communication($user->fresh());

        $this->assertFalse($prefs['weekly_email']);
        $this->assertTrue($prefs['weekly_whatsapp']);
        $this->assertFalse($prefs['weekly_sms']);
    }

    public function test_settings_page_shows_the_toggles_and_the_not_configured_notice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('communication-preferences', false)
            ->assertSee(__('messages.weekly_whatsapp'))
            ->assertSee(__('messages.channel_provider_not_configured'));
    }

    // ------------------------------------------------------------ Providers

    public function test_only_null_messaging_providers_are_bound_and_nothing_is_sent(): void
    {
        $sms = app(SmsVerificationProvider::class);
        $whatsapp = app(WhatsAppProvider::class);

        $this->assertInstanceOf(NullSmsVerificationProvider::class, $sms);
        $this->assertInstanceOf(NullWhatsAppProvider::class, $whatsapp);
        $this->assertFalse($sms->isConfigured());
        $this->assertFalse($whatsapp->isConfigured());

        // A "send" must report failure, never a silent success.
        $this->assertFalse($sms->sendVerificationCode('+2348001112222', '123456'));
        $this->assertFalse($whatsapp->sendMessage('+2348001112222', 'hello'));
    }

    // ----------------------------------------------------------- AI seam

    public function test_the_ai_assistant_is_the_null_implementation(): void
    {
        $assistant = app(AiAssistantInterface::class);

        $this->assertInstanceOf(NullAiAssistant::class, $assistant);
        $this->assertFalse($assistant->isConfigured());

        $response = $assistant->answer('What does Genesis 1:1 mean?');

        $this->assertFalse($response->answered);
        $this->assertSame(__('messages.ai_not_configured'), $response->message);
    }

    public function test_the_ai_endpoint_reports_not_configured_rather_than_answering(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('ai.status'))
            ->assertOk()
            ->assertJson(['configured' => false, 'provider' => 'null']);

        $this->actingAs($user)->postJson(route('ai.ask'), ['prompt' => 'Explain Genesis 1:1'])
            ->assertStatus(503)
            ->assertJson([
                'answered' => false,
                'configured' => false,
                'message' => __('messages.ai_not_configured'),
            ]);
    }

    public function test_the_ai_panel_renders_the_not_configured_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="ai-fab"', false)
            ->assertSee(__('messages.ai_not_configured'));
    }

    // ------------------------------------------------------------- Footer

    public function test_the_footer_renders_a_real_attributed_quote(): void
    {
        $quotes = config('quotes.wealth');

        $this->assertIsArray($quotes);
        $this->assertGreaterThanOrEqual(15, count($quotes));

        foreach ($quotes as $quote) {
            $this->assertNotEmpty($quote['text']);
            $this->assertNotEmpty($quote['author'], 'Every quote must carry an attribution.');
        }

        $index = intdiv(time(), 600) % count($quotes);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="lut-quote"', false)
            ->assertSee($quotes[$index]['text'])
            ->assertSee('not financial advice');
    }

    public function test_the_footer_also_renders_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('id="lut-quote"', false);
    }
}
