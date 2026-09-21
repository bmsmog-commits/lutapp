<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single 'following' state, not a pending/accepted workflow —
        // Phase 22's profile visibility is binary (discoverable or not, see
        // PreferenceService::canViewPublicProfile()), and a non-discoverable
        // profile already 404s for everyone but its owner, so there is no
        // "private but requestable" state that would ever need an approval
        // flow here. Introducing one would be state complexity with nothing
        // to enforce it against.
        Schema::create('user_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['follower_id', 'following_id']);
            $table->index(['following_id']);
            $table->index(['follower_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_connections');
    }
};
