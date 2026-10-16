<?php

namespace App\Services\Messaging;

/**
 * Phase 27.6 — the seam for phone (SMS) verification delivery.
 *
 * Deliberately shaped like the existing PaymentProviderContract: one narrow
 * interface, a null implementation bound by default, a real adapter added later
 * behind config + credentials that live in env — never in code.
 *
 * NO REAL SMS IS SENT ANYWHERE IN THIS CODEBASE. There is exactly one
 * implementation (NullSmsVerificationProvider) and it only writes to the log.
 */
interface SmsVerificationProvider
{
    public function name(): string;

    /**
     * Whether a real, credentialed provider is wired up. When this is false the
     * application must NOT offer phone-only sign-up, and must never imply that
     * a code was delivered.
     */
    public function isConfigured(): bool;

    /**
     * Attempt delivery. Returns false when nothing was actually sent.
     * An implementation must never return true unless a real send succeeded.
     */
    public function sendVerificationCode(string $phone, string $code): bool;
}
