<?php

namespace Zereflab\LaravelBugReports\Commands;

use Illuminate\Console\Command;
use Zereflab\LaravelBugReports\Models\BugReportOccurrence;

class PruneOccurrencesCommand extends Command
{
    protected $signature = 'bug-reports:prune-occurrences {--days= : Delete occurrences older than this many days}';

    protected $description = 'Delete bug report occurrence rows older than the configured retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('bug-reports.occurrences.retention_days', 30));

        if ($days <= 0) {
            $this->info('Occurrence retention is disabled (days <= 0); nothing pruned.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $deleted = BugReportOccurrence::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} occurrence(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
