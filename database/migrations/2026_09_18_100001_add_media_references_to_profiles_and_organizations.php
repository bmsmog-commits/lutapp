<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Phase 9: connects profile photos and organization logos to the Phase 8
    // media_files table instead of storing a raw path directly on the owning
    // table. The legacy `organizations.logo_path` / `user_profiles.profile_image_path`
    // string columns are left in place (unused going forward) rather than dropped —
    // this is additive and preserves any existing data without a destructive change.
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('profile_photo_media_id')->nullable()->after('profile_image_path')
                ->constrained('media_files')->nullOnDelete();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('logo_media_id')->nullable()->after('logo_path')
                ->constrained('media_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_photo_media_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logo_media_id');
        });
    }
};
