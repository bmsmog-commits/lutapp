<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named app_notifications, not notifications — User already `use
        // Notifiable` (Laravel's built-in trait), which reserves the
        // 'notifications' table name/schema for its own DatabaseNotification.
        // Nothing in this app currently uses that trait's notification
        // relation, but a same-named table with a different schema would be a
        // landmine for anyone who later does. Same precedent as jobs_board
        // avoiding collision with the queue's own `jobs` table.
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            // A controlled vocabulary (Notification::TYPES), never free text —
            // see NotificationService.
            $table->string('type', 50);

            $table->string('title');
            $table->text('body')->nullable();

            // A simple (type, id) discriminator pair rather than a true
            // Eloquent morphTo/morph map — the related resource types are
            // known and few, and this avoids registering a polymorphic
            // relation (and its FK/index shape) for what is really just "go
            // look this up and build a URL," resolved in Notification::relatedUrl().
            $table->string('related_type', 30)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
