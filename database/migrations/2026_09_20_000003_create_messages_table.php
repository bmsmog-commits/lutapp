<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 10)->default('text');
            $table->text('body')->nullable();

            // Attachments reuse media_files/MediaStorageService rather than a
            // parallel storage table — same precedent as Resource::cover/file.
            $table->foreignId('media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
