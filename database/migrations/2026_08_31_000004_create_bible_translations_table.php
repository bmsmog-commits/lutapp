<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create bible_translations table if it doesn't exist
        if (!Schema::hasTable('bible_translations')) {
            Schema::create('bible_translations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name');
                $table->string('language', 20);
                $table->text('description')->nullable();
                $table->string('license', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['language', 'is_active']);
            });
        }

        // Add the new relationship before removing the legacy string column.
        if (!Schema::hasColumn('bible_verses', 'translation_id')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->unsignedBigInteger('translation_id')->nullable()->after('verse');
            });
        }

        // Preserve existing translation codes and stop before schema removal if
        // any row cannot be mapped safely to the translation catalog.
        if (Schema::hasColumn('bible_verses', 'translation')) {
            DB::table('bible_verses')
                ->select(['id', 'translation'])
                ->whereNull('translation_id')
                ->orderBy('id')
                ->chunkById(500, function ($verses): void {
                    foreach ($verses as $verse) {
                        $code = strtoupper(trim((string) $verse->translation));
                        $translationId = DB::table('bible_translations')
                            ->where('code', $code)
                            ->value('id');

                        if ($translationId !== null) {
                            DB::table('bible_verses')
                                ->where('id', $verse->id)
                                ->update(['translation_id' => $translationId]);
                        }
                    }
                });

            $unmapped = DB::table('bible_verses')
                ->whereNotNull('translation')
                ->whereNull('translation_id')
                ->count();

            if ($unmapped > 0) {
                throw new \RuntimeException(
                    "Bible translation migration stopped: {$unmapped} verse rows have no matching translation catalog entry."
                );
            }
        }

        // Phase 27.10 — on a FRESH MySQL database `bible_verse_unique` is the
        // only index led by bible_book_id, so MySQL refuses to drop it while
        // the bible_book_id foreign key exists (error 1553). Give the FK a
        // temporary index first (same pattern as 2026_09_17_100001). Not
        // needed on PostgreSQL/SQLite, whose foreign keys do not need one.
        $needsTempFkIndex = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
            && Schema::hasIndex('bible_verses', 'bible_verse_unique')
            && ! Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index');

        if ($needsTempFkIndex) {
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

        if (Schema::hasColumn('bible_verses', 'translation')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropColumn('translation');
            });
        }

        if (! Schema::hasIndex('bible_verses', 'bible_verse_unique')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->unique(['bible_book_id', 'chapter', 'verse', 'translation_id'], 'bible_verse_unique');
            });
        }

        // The recreated unique index (led by bible_book_id) now backs the FK.
        if ($needsTempFkIndex && Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropIndex('bible_verses_book_id_temp_index');
            });
        }

        if (! Schema::hasIndex('bible_verses', 'bible_verses_translation_id_chapter_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->index(['translation_id', 'chapter'], 'bible_verses_translation_id_chapter_index');
            });
        }

        Schema::table('bible_verses', function (Blueprint $table) {
            $table->foreign('translation_id', 'bible_verses_translation_id_foreign')
                ->references('id')
                ->on('bible_translations')
                ->cascadeOnDelete();
        });
    }

    /**
     * Phase 27.10 — real reversal (the previous down() called a Blueprint
     * method that does not exist, so a rollback always failed). Restores the
     * legacy `translation` code column FROM translation_id before dropping
     * it, so no verse loses its translation.
     */
    public function down(): void
    {
        if (! Schema::hasTable('bible_verses')) {
            Schema::dropIfExists('bible_translations');

            return;
        }

        // Match by column: SQLite foreign keys are unnamed.
        $foreign = collect(Schema::getForeignKeys('bible_verses'))
            ->first(fn (array $fk) => $fk['columns'] === ['translation_id']);
        if ($foreign) {
            Schema::table('bible_verses', function (Blueprint $table) use ($foreign) {
                $table->dropForeign($foreign['name'] ?: ['translation_id']);
            });
        }

        if (! Schema::hasColumn('bible_verses', 'translation')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->string('translation', 20)->default('KJV')->after('text');
            });
        }

        if (Schema::hasColumn('bible_verses', 'translation_id') && Schema::hasTable('bible_translations')) {
            DB::table('bible_verses')->whereNotNull('translation_id')->update([
                'translation' => DB::table('bible_translations')
                    ->select('code')
                    ->whereColumn('bible_translations.id', 'bible_verses.translation_id')
                    ->limit(1),
            ]);
        }

        $isMysql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
        if ($isMysql && ! Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->index('bible_book_id', 'bible_verses_book_id_temp_index');
            });
        }

        foreach (['bible_verse_unique' => 'dropUnique', 'bible_verses_translation_id_chapter_index' => 'dropIndex'] as $name => $method) {
            if (Schema::hasIndex('bible_verses', $name)) {
                Schema::table('bible_verses', fn (Blueprint $table) => $table->{$method}($name));
            }
        }

        if (Schema::hasColumn('bible_verses', 'translation_id')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropColumn('translation_id');
            });
        }

        Schema::table('bible_verses', function (Blueprint $table) {
            $table->unique(['bible_book_id', 'chapter', 'verse', 'translation'], 'bible_verse_unique');
            $table->index(['translation', 'chapter'], 'bible_verses_translation_chapter_index');
        });

        if (Schema::hasIndex('bible_verses', 'bible_verses_book_id_temp_index')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropIndex('bible_verses_book_id_temp_index');
            });
        }

        Schema::dropIfExists('bible_translations');
    }
};
