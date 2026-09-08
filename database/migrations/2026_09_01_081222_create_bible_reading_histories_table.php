<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bible_reading_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('bible_book_id')
                ->constrained('bible_books')
                ->cascadeOnDelete();

            $table->unsignedInteger('chapter');

            $table->foreignId('translation_id')
                ->constrained('bible_translations')
                ->cascadeOnDelete();

            $table->timestamp('last_read_at');

            $table->timestamps();

            $table->unique(
                ['user_id', 'bible_book_id', 'chapter', 'translation_id'],
                'bible_reading_histories_unique'
            );

            $table->index(
                ['user_id', 'last_read_at'],
                'bible_reading_user_last_read'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_reading_histories');
    }
};
