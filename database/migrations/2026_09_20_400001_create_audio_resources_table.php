<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_resources', function (Blueprint $table) {
            $table->id();

            // Explicit dual ownership, same precedent as resources/jobs_board —
            // exactly one of these is set (enforced in the controller).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Free-text creator name (an external artist/minister may have no
            // Lutapp account) plus an optional link to an actual account when
            // the creator IS a Lutapp user — avoids forcing a dummy account
            // per artist while still supporting the common "it's me" case.
            $table->string('creator_name')->nullable();
            $table->foreignId('creator_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('category', 50)->nullable();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();

            $table->unsignedInteger('duration_seconds')->nullable();
            $table->date('released_on')->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');

            $table->foreignId('audio_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'visibility']);
            $table->index(['category']);
            $table->index(['language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_resources');
    }
};
