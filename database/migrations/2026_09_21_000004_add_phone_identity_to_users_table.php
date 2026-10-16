<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 27.6 — phone as an OPTIONAL alternative identifier.
 *
 * Purely additive for existing accounts. `users.email` keeps its UNIQUE index
 * and every existing row keeps its address — it only becomes nullable so that a
 * phone-only identity is representable at all. Login, password reset and email
 * verification are unchanged for anyone who has an email address.
 * `phone_verified_at` deliberately mirrors the existing `email_verified_at`
 * convention rather than inventing a parallel scheme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at']);
        });

        // Only reversible while no phone-only account exists; those rows would
        // have no address to put back.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
