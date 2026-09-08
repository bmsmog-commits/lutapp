<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily Prayers Table
        Schema::create('daily_prayers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('category', 50)->default('general'); // 'morning', 'evening', 'general', 'thanksgiving', 'intercession'
            $table->date('published_date')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['published_date', 'is_active']);
        });

        // Daily Teachings Table
        Schema::create('daily_teachings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('topic', 100);
            $table->string('author', 100)->nullable();
            $table->date('published_date')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['published_date', 'is_active', 'topic']);
        });

        // Daily Verses Table (Featured Bible verses, different from BibleVerse)
        Schema::create('daily_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bible_verse_id')->nullable()->constrained('bible_verses')->cascadeOnDelete();
            $table->string('book_name', 50);
            $table->unsignedTinyInteger('chapter');
            $table->unsignedTinyInteger('verse');
            $table->text('verse_text');
            $table->text('commentary')->nullable();
            $table->string('theme', 100)->nullable();
            $table->date('published_date')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['published_date', 'is_active', 'theme']);
        });

        // Daily Quotes Table
        Schema::create('daily_quotes', function (Blueprint $table) {
            $table->id();
            $table->text('quote_text');
            $table->string('author', 100)->nullable();
            $table->string('category', 50)->default('inspiration'); // 'inspiration', 'faith', 'wisdom', 'encouragement', 'perseverance'
            $table->text('reflection')->nullable();
            $table->date('published_date')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['published_date', 'is_active', 'category']);
        });

        // Wealth/Prosperity Teachings Table (RCCG-specific: Redeemed Christian Church of God teachings)
        Schema::create('wealth_teachings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('focus_area', 100); // 'financial', 'business', 'generosity', 'stewardship', 'prosperity'
            $table->string('scripture', 100)->nullable(); // e.g., "Proverbs 22:7"
            $table->string('author', 100)->nullable();
            $table->text('application')->nullable(); // How to apply this teaching
            $table->date('published_date')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['published_date', 'is_active', 'focus_area']);
        });

        // User Daily Content Tracking (tracks what the user has read/accessed)
        Schema::create('user_daily_content_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 50); // 'prayer', 'teaching', 'verse', 'quote', 'wealth'
            $table->unsignedBigInteger('content_id');
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'content_type', 'content_id']);
            $table->index(['user_id', 'content_type', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_daily_content_reads');
        Schema::dropIfExists('wealth_teachings');
        Schema::dropIfExists('daily_quotes');
        Schema::dropIfExists('daily_verses');
        Schema::dropIfExists('daily_teachings');
        Schema::dropIfExists('daily_prayers');
    }
};
