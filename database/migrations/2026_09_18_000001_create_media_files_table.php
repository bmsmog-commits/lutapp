<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            // Explicit ownership, not polymorphic: a file belongs to exactly one of
            // a user or an organization (enforced in MediaStorageService), which is
            // simpler and safer to query/authorize than a generic morph relation for
            // the two owner types this foundation currently needs.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size');
            $table->string('visibility', 20)->default('private');
            $table->string('category', 20);
            $table->string('checksum', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['visibility']);
            $table->index(['checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
