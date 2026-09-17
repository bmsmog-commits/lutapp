<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Phase 2/4 audits confirmed the singular "bible_reading_history" table (created by
    // 2026_08_31_000005) is dead schema: no model, controller, or view references it —
    // the BibleReadingHistory model resolves to the plural "bible_reading_histories"
    // table (created by 2026_09_01_081222) instead. As a safety net, this migration
    // refuses to drop the singular table if it unexpectedly contains any rows.
    public function up(): void
    {
        if (! Schema::hasTable('bible_reading_history')) {
            return;
        }

        $rowCount = DB::table('bible_reading_history')->count();

        if ($rowCount > 0) {
            throw new \RuntimeException(
                "Refusing to drop 'bible_reading_history': it unexpectedly contains {$rowCount} row(s). Investigate before removing this migration guard."
            );
        }

        Schema::dropIfExists('bible_reading_history');
    }

    public function down(): void
    {
        // Not reversible: the table was dead/unused, so there is nothing meaningful
        // to restore. Recreate it manually via the original migration if ever needed.
    }
};
