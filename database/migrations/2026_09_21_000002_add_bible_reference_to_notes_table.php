<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.6 — lets a note optionally carry the Bible reference it was written
 * against, for the Bible Study split-screen workspace.
 *
 * Deliberately a plain nullable display string ("Genesis 1:1 (KJV)") plus the
 * structured book/chapter/translation ids, mirroring the existing
 * bible_bookmarks / bible_highlights column convention rather than inventing a
 * third shape. Every column is nullable, so every existing note is unaffected.
 * Does not touch bible_verses / bible_books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('bible_reference', 120)->nullable()->after('body');
            $table->foreignId('bible_book_id')->nullable()->after('bible_reference')
                ->constrained('bible_books')->nullOnDelete();
            $table->unsignedInteger('bible_chapter')->nullable()->after('bible_book_id');
            $table->foreignId('bible_translation_id')->nullable()->after('bible_chapter')
                ->constrained('bible_translations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bible_translation_id');
            $table->dropColumn('bible_chapter');
            $table->dropConstrainedForeignId('bible_book_id');
            $table->dropColumn('bible_reference');
        });
    }
};
