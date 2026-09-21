<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A separate table from the existing personal `events` table
        // (App\Models\Event) — that system stays untouched. This is an
        // organization-owned activity, not a personal schedule item.
        Schema::create('organization_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('private');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            // Informational only (e.g. "Africa/Lagos") — starts_at/ends_at are
            // still stored/cast the same way every other datetime in this app
            // is; this just lets the UI display the organizer's intended zone
            // instead of assuming every organization is in the same one.
            $table->string('timezone', 60)->nullable();

            $table->string('location_mode', 20)->default('physical');
            // Physical-location fields mirror organizations.country/state/city/
            // address/latitude/longitude exactly — no separate location table.
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Never exposed publicly regardless of event visibility — see
            // OrganizationEvent::publicOnlineUrl().
            $table->string('online_url')->nullable();

            $table->unsignedInteger('capacity')->nullable();

            $table->foreignId('cover_media_id')->nullable()->constrained('media_files')->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'visibility']);
            $table->index(['category']);
            $table->index(['starts_at']);
            $table->index(['country', 'state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_events');
    }
};
