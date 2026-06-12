<?php

namespace Zereflab\LaravelBugReports\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SOLVED = 'solved';

    public const STATUS_IGNORED = 'ignored';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'request' => 'array',
        'slack_messages' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'solved_at' => 'datetime',
        'ignored_at' => 'datetime',
        'status_expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('bug-reports.database.table', parent::getTable());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
