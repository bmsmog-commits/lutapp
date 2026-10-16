<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.6 — 24-hour retention for Bible reading history.
 *
 * `last_read_at` (already present since Phase 8) remains the "read at" moment;
 * this migration adds the matching `expires_at` so expiry is a stored fact
 * rather than a query-time guess, and indexes it for the prune command.
 *
 * Bookmarks and highlights are separate tables and are deliberately NOT given
 * an expiry — they are permanent user data.
 * Does not touch bible_verses / bible_books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bible_reading_histories', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('last_read_at');
            $table->index('expires_at', 'bible_reading_histories_expires_at_index');
        });

        // Backfill existing rows so nothing is treated as "never expiring".
        // Phase 27.10 — per-driver expression; DATE_ADD alone is a syntax
        // error on PostgreSQL even when the table is empty.
        DB::table('bible_reading_histories')
            ->whereNull('expires_at')
            ->update(['expires_at' => DB::raw(match (DB::connection()->getDriverName()) {
                'sqlite' => "datetime(last_read_at, '+24 hours')",
                'pgsql' => "last_read_at + interval '24 hours'",
                'sqlsrv' => 'DATEADD(hour, 24, last_read_at)',
                default => 'DATE_ADD(last_read_at, INTERVAL 24 HOUR)', // mysql, mariadb
            })]);
    }

    public function down(): void
    {
        Schema::table('bible_reading_histories', function (Blueprint $table) {
            $table->dropIndex('bible_reading_histories_expires_at_index');
            $table->dropColumn('expires_at');
        });
    }
};
