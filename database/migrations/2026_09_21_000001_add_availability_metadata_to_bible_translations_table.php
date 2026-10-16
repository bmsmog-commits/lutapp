<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.6 — richer, honest availability metadata for Bible translations.
 *
 * This migration touches ONLY `bible_translations` (catalog metadata).
 * It does not read, write, alter or delete a single row of `bible_verses` or
 * `bible_books`, and it adds ZERO verse text for any translation.
 *
 * `availability_status` is only ever consulted when a translation has no local
 * verse rows — it explains *why* something is unavailable. The presence of real
 * verse rows remains the sole proof of "available" (the Phase 27.5 rule).
 *
 * The NLT/CSB catalog rows live in BibleTranslationSeeder, not here, so this
 * migration stays purely structural.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            if (! Schema::hasColumn('bible_translations', 'license_type')) {
                $table->string('license_type', 48)->nullable()->after('license');
            }
            if (! Schema::hasColumn('bible_translations', 'copyright_holder')) {
                $table->string('copyright_holder')->nullable()->after('license_type');
            }
            if (! Schema::hasColumn('bible_translations', 'availability_status')) {
                $table->string('availability_status', 32)->nullable()->after('is_active');
            }
        });

        // Conservative backfill for rows that already exist in a deployed
        // install. Nothing is newly marked public-domain or redistributable —
        // these updates only restate flags that are already set.
        DB::table('bible_translations')
            ->where('public_domain', true)
            ->whereNull('license_type')
            ->update(['license_type' => 'public_domain']);

        DB::table('bible_translations')
            ->where('public_domain', false)
            ->where('license', 'Proprietary')
            ->whereNull('license_type')
            ->update(['license_type' => 'licensed_remote', 'availability_status' => 'license_required']);

        DB::table('bible_translations')
            ->whereNull('license_type')
            ->update(['license_type' => 'unverified', 'availability_status' => 'not_available']);
    }

    public function down(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            foreach (['availability_status', 'copyright_holder', 'license_type'] as $column) {
                if (Schema::hasColumn('bible_translations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
