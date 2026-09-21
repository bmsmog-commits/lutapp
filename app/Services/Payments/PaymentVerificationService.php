<?php

namespace App\Services\Payments;

use App\Models\GivingTransaction;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The single, canonical path by which a GivingTransaction (and its parent
 * Donation) is ever marked successful. Both the browser callback and the
 * webhook route call this — neither is trusted to decide success on its own,
 * they only trigger this to go ask the provider.
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly NotificationService $notifications,
    ) {
    }

    public function verify(GivingTransaction $transaction): GivingTransaction
    {
        // Cheap early exit before taking a lock — repeated webhooks/callbacks
        // for an already-resolved transaction are the common case.
        if ($transaction->status !== 'pending') {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction) {
            $locked = GivingTransaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            // Re-check after acquiring the lock — a concurrent verify() call
            // (e.g. the webhook and the browser callback arriving together)
            // may have already resolved it while we waited for the lock.
            if ($locked->status !== 'pending') {
                return $locked;
            }

            $result = $this->provider->verify($locked->reference);

            if (! $result->matches($locked->reference, (int) $locked->amount, $locked->currency)) {
                Log::warning('Payment verification mismatch — provider values did not match the initialized transaction.', [
                    'transaction_id' => $locked->id,
                    'reference' => $locked->reference,
                ]);

                return $this->markFailed($locked);
            }

            if (! $result->successful) {
                return $this->markFailed($locked);
            }

            // The unique index on provider_transaction_id is the final,
            // database-enforced idempotency guard — even if two processes
            // both pass every check above, only one insert can win here.
            try {
                $locked->update([
                    'status' => 'successful',
                    'provider_transaction_id' => $result->providerTransactionId,
                    'payment_method' => $result->paymentMethod,
                    'provider_metadata' => $result->safeMetadata,
                    'verified_at' => now(),
                    'completed_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                return $this->markFailed($locked);
            }

            $locked->donation()->update(['status' => 'successful']);
            $locked->refresh();

            // Guest donations have no user_id — there's no recipient to
            // notify in-app (no account to sign into), which is expected.
            if ($locked->donation->user_id) {
                $this->notifications->notify(
                    recipient: $locked->donation->donor,
                    type: 'giving.donation.successful',
                    title: 'Your donation to "'.$locked->campaign->title.'" was successful',
                    organization: $locked->campaign->organization,
                    relatedType: Notification::RELATED_DONATION,
                    relatedId: $locked->donation_id,
                );
            }

            return $locked;
        });
    }

    private function markFailed(GivingTransaction $transaction): GivingTransaction
    {
        $transaction->update(['status' => 'failed', 'verified_at' => now()]);
        $transaction->donation()->update(['status' => 'failed']);

        return $transaction->refresh();
    }
}
