<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Phase 4 licensing audit: the existing free-text "license" column cannot express
    // whether a translation is actually cleared for redistribution, nor link it to the
    // Phase 3 languages catalog. These fields are additive only — nothing existing is
    // removed or renamed, so no data migration is required.
    public function up(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->after('language')->constrained('languages')->nullOnDelete();
            $table->boolean('public_domain')->default(false)->after('license');
            $table->boolean('redistributable')->default(false)->after('public_domain');
            $table->string('license_url')->nullable()->after('redistributable');
            $table->string('source_name')->nullable()->after('license_url');
            $table->string('source_url')->nullable()->after('source_name');
            $table->text('attribution')->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('bible_translations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('language_id');
            $table->dropColumn(['public_domain', 'redistributable', 'license_url', 'source_name', 'source_url', 'attribution']);
        });
    }
};
