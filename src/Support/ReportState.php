<?php

namespace Zereflab\LaravelBugReports\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;
use Zereflab\LaravelBugReports\Models\BugReport;
use Zereflab\LaravelBugReports\Models\BugReportOccurrence;

class ReportState
{
    public const ACTION_IGNORE = 'bug_reports_ignore';

    public const ACTION_SOLVE = 'bug_reports_solve';

    public static function isIgnored(string $fingerprint): bool
    {
        try {
            $report = BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->first();

            if (! $report instanceof BugReport || $report->status !== BugReport::STATUS_IGNORED) {
                return Cache::get(self::statusKey($fingerprint)) === BugReport::STATUS_IGNORED;
            }

            if ($report->status_expires_at && $report->status_expires_at->isPast()) {
                $report->forceFill([
                    'status' => BugReport::STATUS_PENDING,
                    'ignored_at' => null,
                    'status_expires_at' => null,
                ])->save();

                return false;
            }

            return true;
        } catch (Throwable) {
            return Cache::get(self::statusKey($fingerprint)) === BugReport::STATUS_IGNORED;
        }
    }

    public static function ignore(string $fingerprint): void
    {
        $days = (int) config('bug-reports.slack.actions.ignore_ttl_days', 0);
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        try {
            BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->update([
                    'status' => BugReport::STATUS_IGNORED,
                    'ignored_at' => now(),
                    'solved_at' => null,
                    'status_expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]);
        } catch (Throwable) {
            //
        }

        if ($days > 0) {
            Cache::put(self::statusKey($fingerprint), BugReport::STATUS_IGNORED, $expiresAt);

            return;
        }

        // Bounded TTL rather than forever; the database row (status_expires_at
        // null) remains the source of truth for a permanent ignore.
        Cache::put(self::statusKey($fingerprint), BugReport::STATUS_IGNORED, self::cacheTtl());
    }

    public static function solve(string $fingerprint): void
    {
        try {
            BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->update([
                    'status' => BugReport::STATUS_SOLVED,
                    'solved_at' => now(),
                    'ignored_at' => null,
                    'status_expires_at' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable) {
            //
        }

        Cache::put(
            self::statusKey($fingerprint),
            BugReport::STATUS_SOLVED,
            now()->addDays((int) config('bug-reports.slack.actions.solved_ttl_days', 7))
        );

        Cache::forget(self::throttleKey($fingerprint));
    }

    public static function delete(string $fingerprint): void
    {
        try {
            BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->delete();
        } catch (Throwable) {
            //
        }

        Cache::forget(self::statusKey($fingerprint));
        Cache::forget(self::messagesKey($fingerprint));
        Cache::forget(self::throttleKey($fingerprint));
    }

    /**
     * @param  array{channel: string, ts: string, summary: string}  $message
     */
    public static function storeMessage(string $fingerprint, array $message, ?BugReport $report = null): void
    {
        $limit = (int) config('bug-reports.slack.actions.stored_messages', 50);

        $existing = $report instanceof BugReport && is_array($report->slack_messages)
            ? $report->slack_messages
            : self::messages($fingerprint);

        $messages = collect($existing)
            ->reject(fn (array $stored): bool => $stored['channel'] === $message['channel'] && $stored['ts'] === $message['ts'])
            ->push($message)
            ->take(-$limit)
            ->values()
            ->all();

        try {
            BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->update([
                    'slack_messages' => $messages,
                    'updated_at' => now(),
                ]);
        } catch (Throwable) {
            //
        }

        Cache::put(self::messagesKey($fingerprint), $messages, self::cacheTtl());
    }

    /**
     * @return array<int, array{channel: string, ts: string, summary: string}>
     */
    public static function messages(string $fingerprint): array
    {
        try {
            $report = BugReport::query()
                ->where('fingerprint', $fingerprint)
                ->first();

            if ($report instanceof BugReport && is_array($report->slack_messages)) {
                return $report->slack_messages;
            }
        } catch (Throwable) {
            //
        }

        return Cache::get(self::messagesKey($fingerprint), []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function recordOccurrence(string $fingerprint, array $attributes): ?BugReport
    {
        try {
            $now = now();
            $report = BugReport::query()->firstOrNew(['fingerprint' => $fingerprint]);
            $exists = $report->exists;
            $status = $report->status ?: BugReport::STATUS_PENDING;

            if ($status === BugReport::STATUS_SOLVED) {
                $status = BugReport::STATUS_PENDING;
            }

            if ($status === BugReport::STATUS_IGNORED && $report->status_expires_at && $report->status_expires_at->isPast()) {
                $status = BugReport::STATUS_PENDING;
            }

            $report->forceFill([
                'status' => $status,
                'level' => $attributes['level'] ?? null,
                'exception_class' => $attributes['exception_class'] ?? null,
                'message' => $attributes['message'] ?? null,
                'origin' => $attributes['origin'] ?? null,
                'entity' => $attributes['entity'] ?? null,
                'file' => $attributes['file'] ?? null,
                'line' => $attributes['line'] ?? null,
                'context' => $attributes['context'] ?? null,
                'request' => $attributes['request'] ?? null,
                'stack_trace' => $attributes['stack_trace'] ?? null,
                'occurrences' => $exists ? ((int) $report->occurrences + 1) : 1,
                'first_seen_at' => $report->first_seen_at ?: $now,
                'last_seen_at' => $now,
                'solved_at' => $status === BugReport::STATUS_SOLVED ? $report->solved_at : null,
                'ignored_at' => $status === BugReport::STATUS_IGNORED ? $report->ignored_at : null,
                'status_expires_at' => $status === BugReport::STATUS_IGNORED ? $report->status_expires_at : null,
            ])->save();

            BugReportOccurrence::query()->create([
                'bug_report_id' => $report->getKey(),
                'fingerprint' => $fingerprint,
                'level' => $attributes['level'] ?? null,
                'exception_class' => $attributes['exception_class'] ?? null,
                'message' => $attributes['message'] ?? null,
                'origin' => $attributes['origin'] ?? null,
                'occurred_at' => $now,
            ]);

            return $report;
        } catch (Throwable) {
            return null;
        }
    }

    public static function statusKey(string $fingerprint): string
    {
        return self::key("status:{$fingerprint}");
    }

    public static function throttleKey(string $fingerprint): string
    {
        return self::key("alert:{$fingerprint}");
    }

    private static function messagesKey(string $fingerprint): string
    {
        return self::key("messages:{$fingerprint}");
    }

    private static function key(string $suffix): string
    {
        return config('bug-reports.cache_prefix', 'bug-reports').":{$suffix}";
    }

    private static function cacheTtl(): \DateTimeInterface
    {
        return now()->addDays(max(1, (int) config('bug-reports.cache_ttl_days', 30)));
    }
}
