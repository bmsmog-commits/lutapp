<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giving_campaigns', function (Blueprint $table) {
            $table->id();

            // Campaigns are organization-owned only — unlike Resource/Job there is
            // no personal-giving-campaign concept, so this FK is not nullable.
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Integer minor units (e.g. kobo/cents), never floats — see Money.
            $table->unsignedBigInteger('target_amount')->nullable();
            $table->char('currency', 3);

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giving_campaigns');
    }
};
