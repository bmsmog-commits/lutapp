<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language', 10)->default('en');
            $table->string('theme', 20)->default('light');
            $table->string('date_format', 20)->default('Y-m-d');
            $table->string('timezone', 50)->default('UTC');
            $table->boolean('notifications_enabled')->default(true);
            $table->json('notification_preferences')->nullable();
            $table->timestamps();

            $table->index(['language', 'theme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
