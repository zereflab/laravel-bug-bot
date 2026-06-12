<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $bugReportsTable = config('bug-reports.database.table', 'bug_reports');
        $occurrencesTable = $bugReportsTable.'_occurrences';

        Schema::create($bugReportsTable, function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('level', 20)->nullable()->index();
            $table->string('exception_class')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('origin')->nullable()->index();
            $table->string('entity')->nullable();
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->json('context')->nullable();
            $table->json('request')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->json('slack_messages')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('status_expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'last_seen_at']);
            $table->index(['origin', 'last_seen_at']);
        });

        Schema::create($occurrencesTable, function (Blueprint $table) use ($bugReportsTable): void {
            $table->id();
            $table->foreignId('bug_report_id')->constrained($bugReportsTable)->cascadeOnDelete();
            $table->string('fingerprint', 64)->index();
            $table->string('level', 20)->nullable()->index();
            $table->string('exception_class')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('origin')->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['origin', 'occurred_at']);
        });
    }

    public function down(): void
    {
        $bugReportsTable = config('bug-reports.database.table', 'bug_reports');

        Schema::dropIfExists($bugReportsTable.'_occurrences');
        Schema::dropIfExists($bugReportsTable);
    }
};
