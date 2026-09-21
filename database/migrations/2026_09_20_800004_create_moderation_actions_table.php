<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The moderation audit trail — append-only in practice (nothing in
        // this codebase updates or deletes a row here). Kept separate from
        // moderation_notes: notes are a reviewer's free-form commentary,
        // this is a structured record of what actually changed.
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();

            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');

            $table->string('action', 40);
            $table->text('reason')->nullable();
            $table->string('previous_state', 40)->nullable();
            $table->string('new_state', 40)->nullable();

            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['moderator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
    }
};
