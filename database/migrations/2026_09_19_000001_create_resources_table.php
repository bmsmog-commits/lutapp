<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();

            // Explicit dual ownership, matching the media_files precedent from
            // Phase 8/9 rather than a polymorphic relation — exactly one of these
            // is set (enforced in ResourceController), which keeps ownership
            // queries and policies simple.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            // Single table with a type discriminator rather than a separate
            // `books` table — "book" is the first resource type, but devotionals,
            // sermon notes, etc. described in Phase 11 share the same shape.
            $table->string('type', 30)->default('book');

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->date('published_on')->nullable();

            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->string('category', 50)->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');

            $table->foreignId('cover_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->foreignId('file_media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['type', 'status', 'visibility']);
            $table->index(['category']);
            $table->index(['language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
