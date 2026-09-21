<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giving_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->cascadeOnDelete();

            // Denormalized from the donation purely for isolation-query/index
            // convenience, same precedent as donations.organization_id.
            $table->foreignId('campaign_id')->constrained('giving_campaigns')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 30);

            // Our own per-attempt reference, sent to the provider at
            // initialization and used to look the row up again at verification
            // time — never trust a client-supplied ID for that lookup.
            $table->string('reference')->unique();

            $table->string('provider_reference')->nullable();

            // Unique (not just indexed) so the same provider transaction id can
            // never be attached to two different rows — the core idempotency
            // guard against a replayed webhook crediting twice.
            $table->string('provider_transaction_id')->nullable()->unique();

            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 30)->nullable();

            // Only a safe, deliberately-curated subset of the provider's response
            // — never the raw payload, which could carry sensitive fields.
            $table->json('provider_metadata')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['donation_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giving_transactions');
    }
};
