<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_completed', 'due_date']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type', 30)->default('meeting');
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'event_type']);
        });

        Schema::create('hymns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('hymn_number', 50)->nullable();
            $table->string('author')->nullable();
            $table->text('lyrics');
            $table->timestamps();

            $table->index(['user_id', 'title']);
        });

        Schema::create('bible_books', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('sort_order')->unique();
            $table->string('name');
            $table->string('abbreviation', 20);
            $table->string('testament', 20);
            $table->unsignedTinyInteger('chapters_count');
            $table->timestamps();
        });

        Schema::create('bible_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bible_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('chapter');
            $table->unsignedTinyInteger('verse');
            $table->text('text');
            $table->string('translation', 20)->default('KJV');
            $table->timestamps();

            $table->unique(['bible_book_id', 'chapter', 'verse', 'translation'], 'bible_verse_unique');
            $table->index(['translation', 'chapter']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('bible_verses', function (Blueprint $table) {
                $table->fullText('text');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_verses');
        Schema::dropIfExists('bible_books');
        Schema::dropIfExists('hymns');
        Schema::dropIfExists('events');
        Schema::dropIfExists('todo_items');
    }
};
