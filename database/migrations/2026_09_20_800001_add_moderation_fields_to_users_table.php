<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Deliberately a plain boolean, not a Spatie role — platform
            // moderation is a distinct authority from the existing per-
            // organization Owner/Admin roles (which are team-scoped and
            // exist per organization), and must never be reachable merely by
            // holding an organization role. See ModerationService/User::isModerator().
            $table->boolean('is_moderator')->default(false)->after('remember_token');

            // active: normal. restricted: admin-imposed limits on new
            // outbound interaction (follow/message) — profile/content stay
            // visible. suspended / deactivated: both fully hidden from
            // public discovery and blocked from logging in; kept as
            // distinct values (not one "disabled" flag) so a future
            // self-service deactivation path can reuse this same column
            // without another migration, and so a moderator can tell an
            // admin-imposed suspension apart from an account the user
            // disabled themselves.
            $table->string('account_status', 20)->default('active')->after('is_moderator');
            $table->text('account_status_reason')->nullable()->after('account_status');
            $table->timestamp('account_status_changed_at')->nullable()->after('account_status_reason');

            $table->index(['account_status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_moderator', 'account_status', 'account_status_reason', 'account_status_changed_at']);
        });
    }
};
