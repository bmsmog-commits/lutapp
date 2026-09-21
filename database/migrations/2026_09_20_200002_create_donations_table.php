<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('giving_campaigns')->cascadeOnDelete();

            // Denormalized alongside campaign_id purely so organization-isolation
            // queries/policies never need to join through giving_campaigns first.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Null user_id = guest donor; donor_name/email capture who to credit
            // on a receipt without forcing an account to be created.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();

            // The donation intent's amount — fixed once, independent of however
            // many payment attempts (giving_transactions rows) it takes to fulfill it.
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);

            // Stable across retries — one donation, potentially several attempts.
            $table->string('reference')->unique();
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
