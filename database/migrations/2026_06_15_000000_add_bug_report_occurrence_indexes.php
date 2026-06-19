<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $occurrencesTable = config('bug-reports.database.table', 'bug_reports').'_occurrences';

        if (! Schema::hasTable($occurrencesTable)) {
            return;
        }

        Schema::table($occurrencesTable, function (Blueprint $table): void {
            // Dashboard aggregates filter by occurred_at first, then group by
            // exception_class / origin. Leading occurred_at matches that shape.
            $table->index(['occurred_at', 'exception_class'], 'bro_occurred_exception_idx');
            $table->index(['occurred_at', 'origin'], 'bro_occurred_origin_idx');
        });
    }

    public function down(): void
    {
        $occurrencesTable = config('bug-reports.database.table', 'bug_reports').'_occurrences';

        if (! Schema::hasTable($occurrencesTable)) {
            return;
        }

        Schema::table($occurrencesTable, function (Blueprint $table): void {
            $table->dropIndex('bro_occurred_exception_idx');
            $table->dropIndex('bro_occurred_origin_idx');
        });
    }
};
