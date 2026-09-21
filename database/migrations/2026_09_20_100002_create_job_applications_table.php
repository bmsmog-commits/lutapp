<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs_board')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();

            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');

            // Set only once an application is accepted — links to the existing
            // Phase 12 Conversation rather than a job-specific chat table. Left
            // null while pending/rejected/withdrawn.
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();

            $table->timestamps();

            // No hard unique constraint: a withdrawn or rejected application must
            // not permanently block a user from ever re-applying, so "no duplicate
            // active application" is enforced in the controller instead (checked
            // against pending/accepted rows only). This index just keeps that
            // lookup, and the applicant's own application list, fast.
            $table->index(['job_id', 'applicant_id']);
            $table->index(['applicant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
