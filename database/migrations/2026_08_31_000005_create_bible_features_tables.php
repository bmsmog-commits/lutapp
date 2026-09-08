<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chapter');
            $table->unsignedTinyInteger('verse')->nullable();  // null for entire chapter
            $table->foreignId('translation_id')->constrained('bible_translations')->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            // User can bookmark same verse in different translations
            $table->unique(['user_id', 'bible_book_id', 'chapter', 'verse', 'translation_id'], 'bible_bookmark_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('bible_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chapter');
            $table->unsignedTinyInteger('verse');
            $table->foreignId('translation_id')->constrained('bible_translations')->cascadeOnDelete();
            $table->string('color', 20)->default('yellow');  // 'yellow', 'red', 'blue', 'green'
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'bible_book_id', 'chapter', 'verse', 'translation_id'], 'bible_highlight_unique');
            $table->index(['user_id', 'color']);
        });

        Schema::create('bible_reading_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chapter');
            $table->foreignId('translation_id')->constrained('bible_translations')->cascadeOnDelete();
            $table->dateTime('last_read_at');
            $table->timestamps();

            // Track reading progress per user/book/chapter/translation
            $table->unique(['user_id', 'bible_book_id', 'chapter', 'translation_id'], 'bible_reading_unique');
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_reading_history');
        Schema::dropIfExists('bible_highlights');
        Schema::dropIfExists('bible_bookmarks');
    }
};
