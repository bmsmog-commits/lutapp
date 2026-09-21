<?php

namespace App\Http\Controllers;

use App\Models\GivingTransaction;
use App\Services\Payments\PaymentProviderFactory;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $provider): Response
    {
        $adapter = PaymentProviderFactory::make($provider);

        $reference = $adapter->resolveWebhookReference($request);

        // Invalid/missing signature — reject outright. Never fall back to
        // reading the payload's own claimed reference/status regardless.
        if ($reference === null) {
            return response('Invalid signature.', 401);
        }

        $transaction = GivingTransaction::where('reference', $reference)->first();

        // An unknown reference is acknowledged (200) rather than erroring, so
        // the provider doesn't retry indefinitely for a webhook that will
        // never resolve to anything on our side — but nothing is processed.
        if ($transaction === null) {
            return response('ok', 200);
        }

        app(PaymentVerificationService::class, ['provider' => $adapter])->verify($transaction);

        return response('ok', 200);
    }
}
