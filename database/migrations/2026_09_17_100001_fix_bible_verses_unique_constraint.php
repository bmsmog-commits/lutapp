<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Phase 4 audit found the live "bible_verse_unique" constraint only covers
    // (bible_book_id, chapter, verse) — translation_id was silently dropped from it
    // during the 2026_08_31_000004 migration, and a stray single-column leftover
    // index from the pre-translation_id schema was never cleaned up. Both are
    // corrected here. This is idempotent: safe to run whether or not the earlier
    // migration already fixed it on a given environment.
    public function up(): void
    {
        if ($this->uniqueCoversTranslationId()) {
            return;
        }

        // bible_book_id's foreign key relies on bible_verse_unique as its backing
        // index, so a temporary plain index keeps the FK valid while we swap it out.
        if (! Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->index('bible_book_id', 'bible_verses_book_id_temp_index');
            });
        }

        if (Schema::hasIndex('bible_verses', 'bible_verse_unique')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropUnique('bible_verse_unique');
            });
        }

        if (Schema::hasIndex('bible_verses', 'bible_verses_translation_chapter_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropIndex('bible_verses_translation_chapter_index');
            });
        }

        Schema::table('bible_verses', function (Blueprint $table) {
            $table->unique(['bible_book_id', 'chapter', 'verse', 'translation_id'], 'bible_verse_unique');
        });

        if (Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropIndex('bible_verses_book_id_temp_index');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting to a constraint that does not
        // enforce translation-scoped uniqueness would reintroduce the data-integrity
        // gap this migration exists to close.
    }

    private function uniqueCoversTranslationId(): bool
    {
        if (! Schema::hasIndex('bible_verses', 'bible_verse_unique')) {
            return false;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $columns = collect($connection->select("PRAGMA index_info('bible_verse_unique')"))
                ->pluck('name');
        } else {
            $columns = collect(DB::select("SHOW INDEX FROM bible_verses WHERE Key_name = 'bible_verse_unique'"))
                ->pluck('Column_name');
        }

        return $columns->contains('translation_id');
    }
};
