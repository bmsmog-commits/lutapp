<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

            // A simple (type, id) discriminator pair, not a true Eloquent
            // morphTo — same precedent as app_notifications.related_type
            // (Phase 18): the reportable types are known and few, and this
            // avoids a polymorphic FK/index shape for what's really just
            // "go look this up," resolved in Report::target().
            $table->string('reportable_type', 30);
            $table->unsignedBigInteger('reportable_id');

            $table->string('reason', 30);
            $table->text('description')->nullable();

            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status']);
            $table->index(['reporter_id']);
            // One open report per (reporter, target) — resubmitting the same
            // target while an earlier report is still pending/under_review
            // is a duplicate, not a new signal; a fresh report is allowed
            // again only once the prior one is resolved/dismissed (handled
            // in ReportService, not by this constraint alone, since it must
            // only apply to open statuses).
            $table->index(['reporter_id', 'reportable_type', 'reportable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
