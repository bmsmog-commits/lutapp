<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs_board', function (Blueprint $table) {
            $table->id();

            // Explicit dual ownership, matching the resources/media_files precedent
            // from Phase 8/11 rather than a polymorphic relation — exactly one of
            // these is set (enforced in JobController).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');

            $table->string('work_mode', 20)->default('remote');
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();

            $table->string('budget_type', 20)->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();

            $table->date('application_deadline')->nullable();

            // A single optional brief/spec attachment, same one-file-per-purpose
            // pattern as Resource::cover/file.
            $table->foreignId('attachment_media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'visibility']);
            $table->index(['category']);
            $table->index(['work_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs_board');
    }
};
