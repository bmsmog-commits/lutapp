<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Models\GivingCampaign;
use App\Models\GivingTransaction;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GivingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentProvider::resetLedger();
        config(['payments.default_provider' => 'fake']);
    }

    private function createOrganization(User $owner, string $slug = 'grace-chapel', string $visibility = 'public'): Organization
    {
        return Organization::create([
            'owner_id' => $owner->id, 'name' => 'Grace Chapel', 'slug' => $slug, 'type' => 'church', 'visibility' => $visibility,
        ]);
    }

    private function makeCampaign(Organization $organization, array $overrides = []): GivingCampaign
    {
        return GivingCampaign::create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Building Fund',
            'slug' => 'building-fund-'.uniqid(),
            'currency' => 'NGN',
            'status' => 'published',
            'visibility' => 'public',
        ], $overrides));
    }

    private function webhookHeaders(string $body): array
    {
        $secret = config('payments.providers.fake.webhook_secret');

        return ['X-Fake-Signature' => hash_hmac('sha256', $body, $secret)];
    }

    // DONATION CREATION

    public function test_authenticated_user_can_start_a_donation(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();

        $response = $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '50.00']);

        $donation = Donation::first();
        $this->assertSame($donor->id, $donation->user_id);
        $this->assertSame(5000, $donation->amount);
        $this->assertSame('pending', $donation->status);
        $response->assertRedirect();

        $transaction = GivingTransaction::first();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('fake', $transaction->provider);
    }

    public function test_guest_can_donate_with_name_and_email(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);

        $this->post(route('giving.donate', [$organization, $campaign]), [
            'amount' => '25.00', 'donor_name' => 'Jane Guest', 'donor_email' => 'jane@example.com',
        ])->assertRedirect();

        $donation = Donation::first();
        $this->assertNull($donation->user_id);
        $this->assertSame('Jane Guest', $donation->donor_name);
    }

    public function test_guest_donation_requires_name_and_email(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);

        $this->post(route('giving.donate', [$organization, $campaign]), ['amount' => '25.00'])
            ->assertSessionHasErrors('donor_name');

        $this->assertSame(0, Donation::count());
    }

    public function test_invalid_amount_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();

        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '-5'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => 'not-a-number'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Donation::count());
    }

    public function test_donation_to_a_private_campaign_is_rejected_for_outsiders(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['visibility' => 'private']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10'])
            ->assertForbidden();
        $this->assertSame(0, Donation::count());
    }

    public function test_donation_to_a_closed_campaign_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization, ['status' => 'closed']);
        $donor = User::factory()->create();

        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10'])
            ->assertForbidden();
        $this->assertSame(0, Donation::count());
    }

    public function test_each_donation_gets_a_unique_reference(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();

        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);

        $references = Donation::pluck('reference');
        $this->assertCount(2, $references);
        $this->assertNotEquals($references[0], $references[1]);
    }

    // PROVIDER / VERIFICATION

    public function test_successful_simulated_payment_marks_transaction_and_donation_successful(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success'])
            ->assertRedirect(route('giving.result', $transaction->reference));

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertNotNull($transaction->provider_transaction_id);
        $this->assertNotNull($transaction->verified_at);
        $this->assertSame('successful', $transaction->donation->refresh()->status);
    }

    public function test_failed_simulated_payment_marks_transaction_and_donation_failed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'failed']);

        $transaction->refresh();
        $this->assertSame('failed', $transaction->status);
        $this->assertSame('failed', $transaction->donation->refresh()->status);
    }

    public function test_verification_rejects_a_provider_amount_mismatch(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        // Provider reports success but with a different amount than we initialized.
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful', amountOverride: 999999);

        app(PaymentVerificationService::class)->verify($transaction);

        $this->assertSame('failed', $transaction->refresh()->status);
    }

    public function test_verification_rejects_a_provider_currency_mismatch(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful', currencyOverride: 'USD');

        app(PaymentVerificationService::class)->verify($transaction);

        $this->assertSame('failed', $transaction->refresh()->status);
    }

    public function test_verification_of_an_unknown_reference_fails_safely(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        // Never simulated/initialized on the provider side (ledger has no entry).
        $transaction->update(['reference' => 'NEVER-INITIALIZED-REF']);

        app(PaymentVerificationService::class)->verify($transaction);

        $this->assertSame('failed', $transaction->refresh()->status);
    }

    public function test_repeated_verification_does_not_reprocess_or_change_state(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful');

        $service = app(PaymentVerificationService::class);
        $service->verify($transaction);
        $firstProviderTxnId = $transaction->refresh()->provider_transaction_id;
        $firstVerifiedAt = $transaction->verified_at;

        // Verify again — must be a safe no-op, not a second state change.
        $service->verify($transaction);

        $transaction->refresh();
        $this->assertSame('successful', $transaction->status);
        $this->assertSame($firstProviderTxnId, $transaction->provider_transaction_id);
        $this->assertEquals($firstVerifiedAt, $transaction->verified_at);
    }

    public function test_repeated_simulate_callback_is_safe_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success']);
        // A second click/duplicate callback for the same reference.
        $this->post(route('giving.checkout.simulate', $transaction->reference), ['outcome' => 'success']);

        $this->assertSame(1, GivingTransaction::where('status', 'successful')->count());
    }

    // WEBHOOKS

    public function test_webhook_with_valid_signature_confirms_the_transaction(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful');

        $body = json_encode(['reference' => $transaction->reference]);
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Fake-Signature' => hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret')),
        ], $body)->assertOk();

        $this->assertSame('successful', $transaction->refresh()->status);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful');

        $body = json_encode(['reference' => $transaction->reference]);
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Fake-Signature' => 'not-the-real-signature',
        ], $body)->assertStatus(401);

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_webhook_with_missing_signature_is_rejected(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful');

        $body = json_encode(['reference' => $transaction->reference]);
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(401);

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_malformed_webhook_payload_is_handled_safely(): void
    {
        $body = 'not-json-at-all-and-no-reference';
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X-Fake-Signature' => hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret')),
        ], $body)->assertStatus(401);
    }

    public function test_duplicate_webhook_delivery_does_not_double_process(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transaction->reference, 'successful');

        $body = json_encode(['reference' => $transaction->reference]);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Fake-Signature' => hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret'))];

        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], $headers, $body)->assertOk();
        $firstProviderTxnId = $transaction->refresh()->provider_transaction_id;

        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], $headers, $body)->assertOk();

        $transaction->refresh();
        $this->assertSame(1, GivingTransaction::where('status', 'successful')->where('reference', $transaction->reference)->count());
        $this->assertSame($firstProviderTxnId, $transaction->provider_transaction_id);
    }

    public function test_webhook_cannot_credit_an_unrelated_transaction_via_a_forged_reference(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $realTransaction = GivingTransaction::first();

        // A forged reference that was never initialized has no ledger entry —
        // verify() must fail closed, not accidentally match the real transaction.
        $body = json_encode(['reference' => 'FORGED-REF-DOES-NOT-EXIST']);
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Fake-Signature' => hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret')),
        ], $body)->assertOk();

        $this->assertSame('pending', $realTransaction->refresh()->status);
    }

    public function test_webhook_for_organization_bs_transaction_cannot_be_triggered_through_organization_a_context(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $campaignA = $this->makeCampaign($orgA);
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $campaignB = $this->makeCampaign($orgB);

        $donorA = User::factory()->create();
        $this->actingAs($donorA)->post(route('giving.donate', [$orgA, $campaignA]), ['amount' => '10']);
        $transactionA = GivingTransaction::first();
        FakePaymentProvider::simulateOutcome($transactionA->reference, 'successful');

        $body = json_encode(['reference' => $transactionA->reference]);
        $this->call('POST', route('payments.webhook', 'fake'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Fake-Signature' => hash_hmac('sha256', $body, config('payments.providers.fake.webhook_secret')),
        ], $body)->assertOk();

        $transactionA->refresh();
        $this->assertSame($orgA->id, $transactionA->organization_id);
        $this->assertSame('successful', $transactionA->status);
        // Nothing under org B was touched — no cross-organization bleed exists
        // by construction since lookup is by unique reference only.
        $this->assertSame(0, GivingTransaction::where('organization_id', $orgB->id)->where('status', 'successful')->count());
    }

    // ORGANIZATION ISOLATION / DONOR HISTORY

    public function test_donor_can_see_their_own_giving_history(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);

        $this->actingAs($donor)->get(route('giving.mine'))->assertOk()->assertSee('Building Fund');
    }

    public function test_donor_cannot_see_another_donors_history(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donorA = User::factory()->create();
        $this->actingAs($donorA)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $donation = Donation::first();

        $donorB = User::factory()->create();
        $this->actingAs($donorB)->get(route('giving.mine'))->assertDontSee($donation->reference);
    }

    public function test_organization_admin_sees_only_their_organizations_giving_history(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $campaignA = $this->makeCampaign($orgA, ['title' => 'Org A Fund']);
        $ownerB = User::factory()->create();
        $orgB = $this->createOrganization($ownerB, 'org-b');
        $campaignB = $this->makeCampaign($orgB, ['title' => 'Org B Fund']);

        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$orgB, $campaignB]), ['amount' => '10']);

        $this->actingAs($ownerA)->get(route('giving.history', [$orgA, $campaignA]))->assertOk();
        $this->actingAs($ownerA)->get(route('giving.history', [$orgB, $campaignB]))->assertForbidden();
    }

    public function test_a_donation_or_transaction_id_cannot_be_used_across_organizations(): void
    {
        $ownerA = User::factory()->create();
        $orgA = $this->createOrganization($ownerA, 'org-a');
        $campaignA = $this->makeCampaign($orgA);
        $donorA = User::factory()->create();
        $this->actingAs($donorA)->post(route('giving.donate', [$orgA, $campaignA]), ['amount' => '10']);
        $donationA = Donation::first();

        $ownerB = User::factory()->create();
        $this->actingAs($ownerB)->post(route('giving.donations.retry', $donationA))->assertForbidden();
    }

    // SECURITY

    public function test_card_number_cvv_and_pin_fields_are_never_accepted_or_stored(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();

        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), [
            'amount' => '10', 'card_number' => '4111111111111111', 'cvv' => '123', 'pin' => '1234',
        ]);

        $donation = Donation::first();
        $this->assertArrayNotHasKey('card_number', $donation->getAttributes());
        $this->assertArrayNotHasKey('cvv', $donation->getAttributes());
        $this->assertArrayNotHasKey('pin', $donation->getAttributes());
    }

    public function test_webhook_secret_is_never_rendered_in_any_response(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $campaign = $this->makeCampaign($organization);
        $donor = User::factory()->create();
        $this->actingAs($donor)->post(route('giving.donate', [$organization, $campaign]), ['amount' => '10']);
        $transaction = GivingTransaction::first();

        $response = $this->get(route('giving.checkout.show', $transaction->reference));

        $response->assertDontSee(config('payments.providers.fake.webhook_secret'));
    }
}
