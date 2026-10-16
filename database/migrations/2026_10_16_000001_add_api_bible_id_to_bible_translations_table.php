<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.8 — link a catalog translation to its API.Bible Bible ID.
 *
 * Purely structural: touches only `bible_translations`. No verse text is
 * read, written or deleted, and the local KJV corpus in `bible_verses` is
 * untouched. Remote text is never persisted to `bible_verses` — it is only
 * held in the short-lived application cache that API.Bible's terms permit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            if (! Schema::hasColumn('bible_translations', 'api_bible_id')) {
                $table->string('api_bible_id', 64)->nullable()->after('source_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            if (Schema::hasColumn('bible_translations', 'api_bible_id')) {
                $table->dropColumn('api_bible_id');
            }
        });
    }
};
