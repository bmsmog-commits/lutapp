<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // 'direct' is the only type Phase 12 creates; the column exists now so
            // a future 'group' type doesn't require a schema change, only new
            // code paths.
            $table->string('type', 20)->default('direct');

            // Deterministic "{min_user_id}-{max_user_id}" key, unique per pair —
            // the race-safe way to guarantee at most one direct conversation
            // between any two users (a unique index catches concurrent inserts
            // that a SELECT-then-INSERT check alone would miss). Null for any
            // future non-direct conversation type.
            $table->string('direct_key', 40)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
