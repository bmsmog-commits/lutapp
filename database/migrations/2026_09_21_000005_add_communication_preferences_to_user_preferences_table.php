<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.6 — weekly digest channel preferences.
 *
 * Follows the exact Phase 21 convention: one nullable JSON column on the
 * existing user_preferences table, read through PreferenceService. Nullable, so
 * every existing row keeps its current behaviour.
 *
 * STORAGE ONLY. Nothing in this application sends a weekly email, SMS or
 * WhatsApp message — these keys exist for a future automation layer to read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('communication_preferences')->nullable()->after('dashboard_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('communication_preferences');
        });
    }
};
