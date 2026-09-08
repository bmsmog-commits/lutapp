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

    public function down(): void
    {
        if (Schema::hasTable('bible_verses')) {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->dropForeignKeyConstraints();
                if (Schema::hasColumn('bible_verses', 'translation_id')) {
                    $table->dropColumn('translation_id');
                }
            });
        }

        Schema::dropIfExists('bible_translations');
    }
};
