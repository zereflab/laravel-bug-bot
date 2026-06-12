<?php

namespace Zereflab\LaravelBugReports\Models;

use Illuminate\Database\Eloquent\Model;

class BugReportOccurrence extends Model
{
    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('bug-reports.database.table', 'bug_reports').'_occurrences';
    }
}
