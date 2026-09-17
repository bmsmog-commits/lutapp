<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 30);
            $table->string('logo_path')->nullable();
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('social_links')->nullable();
            $table->string('visibility', 20)->default('public');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['country', 'state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
