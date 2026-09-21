<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('audio_collections')->cascadeOnDelete();
            $table->foreignId('audio_resource_id')->constrained('audio_resources')->cascadeOnDelete();

            // Explicit position, not created_at — reordering must be a
            // deliberate write, not a side effect of insertion order.
            $table->unsignedInteger('position')->default(1);

            $table->timestamps();

            $table->unique(['collection_id', 'audio_resource_id']);
            $table->index(['collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_collection_items');
    }
};
