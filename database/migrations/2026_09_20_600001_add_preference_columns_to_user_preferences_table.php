<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extends the existing user_preferences table (language/theme/
        // notification_preferences already lived here since Phase 8/9) rather
        // than creating a second preferences table — all nullable, so every
        // existing row keeps behaving exactly as it does today until a user
        // explicitly sets one of these in Phase 21's settings center.
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('privacy_preferences')->nullable()->after('notification_preferences');
            $table->json('bible_preferences')->nullable()->after('privacy_preferences');
            $table->json('audio_preferences')->nullable()->after('bible_preferences');
            $table->json('dashboard_preferences')->nullable()->after('audio_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn(['privacy_preferences', 'bible_preferences', 'audio_preferences', 'dashboard_preferences']);
        });
    }
};
