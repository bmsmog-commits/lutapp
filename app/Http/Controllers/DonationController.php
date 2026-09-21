<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\GivingCampaign;
use App\Models\GivingTransaction;
use App\Models\Organization;
use App\Services\Payments\PaymentProviderContract;
use App\Services\Payments\PaymentVerificationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DonationController extends Controller
{
    public function store(
        Request $request,
        Organization $organization,
        GivingCampaign $campaign,
        PaymentProviderContract $provider,
    ): RedirectResponse {
        abort_unless($campaign->organization_id === $organization->id, 404);
        $this->authorize('view', $campaign);

        if (! $campaign->acceptsDonations()) {
            return back()->withErrors(['amount' => 'This campaign is not currently accepting donations.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'donor_name' => ['nullable', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user && (empty($data['donor_name']) || empty($data['donor_email']))) {
            return back()->withErrors(['donor_name' => 'Please provide your name and email to give as a guest.']);
        }

        // Currency is always the campaign's own — the donor never selects it,
        // which sidesteps any need for currency conversion entirely.
        $donation = Donation::create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'user_id' => $user?->id,
            'donor_name' => $user ? null : $data['donor_name'],
            'donor_email' => $user ? null : $data['donor_email'],
            'amount' => Money::toMinorUnits($data['amount']),
            'currency' => $campaign->currency,
            'reference' => 'DON-'.Str::upper(Str::random(16)),
            'status' => 'pending',
        ]);

        $checkoutUrl = $this->startPaymentAttempt($donation, $provider);

        return redirect()->away($checkoutUrl);
    }

    // A failed/abandoned attempt may be retried without creating a second
    // Donation — this issues a fresh GivingTransaction under the same one.
    public function retry(Request $request, Donation $donation, PaymentProviderContract $provider): RedirectResponse
    {
        $this->authorize('view', $donation);

        if (! $donation->acceptsNewAttempt()) {
            return back()->withErrors(['amount' => 'This donation cannot be retried.']);
        }

        $checkoutUrl = $this->startPaymentAttempt($donation, $provider);

        return redirect()->away($checkoutUrl);
    }

    public function checkout(Request $request, string $reference): View
    {
        $transaction = GivingTransaction::where('reference', $reference)->firstOrFail();

        return view('giving.checkout', ['transaction' => $transaction]);
    }

    // The fake provider's stand-in for a real checkout page's browser
    // callback. The $outcome the browser posts back is NEVER trusted directly
    // — it only tells the fake provider what to report when asked for real,
    // then PaymentVerificationService performs the actual (in-process)
    // server-to-server verification exactly as a webhook would.
    public function simulate(
        Request $request,
        string $reference,
        PaymentProviderContract $provider,
        PaymentVerificationService $verificationService,
    ): RedirectResponse {
        $transaction = GivingTransaction::where('reference', $reference)->firstOrFail();

        $outcome = $request->input('outcome') === 'success' ? 'successful' : 'failed';

        if ($transaction->status === 'pending' && $provider instanceof \App\Services\Payments\FakePaymentProvider) {
            \App\Services\Payments\FakePaymentProvider::simulateOutcome($reference, $outcome);
        }

        $verificationService->verify($transaction);

        return redirect()->route('giving.result', $reference);
    }

    public function result(Request $request, string $reference): View
    {
        $transaction = GivingTransaction::where('reference', $reference)->with('donation')->firstOrFail();

        return view('giving.result', ['transaction' => $transaction]);
    }

    public function mine(Request $request): View
    {
        $donations = $request->user()->donations()->with(['campaign.organization'])->latest()->paginate(20);

        return view('giving.mine', ['donations' => $donations]);
    }

    private function startPaymentAttempt(Donation $donation, PaymentProviderContract $provider): string
    {
        $transaction = GivingTransaction::create([
            'donation_id' => $donation->id,
            'campaign_id' => $donation->campaign_id,
            'organization_id' => $donation->organization_id,
            'provider' => $provider->name(),
            'reference' => $donation->reference.'-'.Str::upper(Str::random(6)),
            'amount' => $donation->amount,
            'currency' => $donation->currency,
            'status' => 'pending',
        ]);

        $initialization = $provider->initialize($transaction);

        $transaction->update(['provider_reference' => $initialization->providerReference]);

        return $initialization->checkoutUrl;
    }
}
