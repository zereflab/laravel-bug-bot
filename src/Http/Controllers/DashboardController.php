<?php

namespace Zereflab\LaravelBugReports\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Zereflab\LaravelBugReports\Models\BugReport;
use Zereflab\LaravelBugReports\Models\BugReportOccurrence;
use Zereflab\LaravelBugReports\Support\ReportState;

class DashboardController extends Controller
{
    public function index(Request $request, string $status = 'all'): View
    {
        $this->authorizeDashboard($request);

        if (! in_array($status, ['all', BugReport::STATUS_PENDING, BugReport::STATUS_SOLVED, BugReport::STATUS_IGNORED], true)) {
            abort(404);
        }

        if (! $this->tableExists()) {
            return view('bug-reports::dashboard.missing-migration', [
                'message' => 'The bug reports table was not found. Run "php artisan migrate" to create it.',
            ]);
        }

        try {
            $reports = BugReport::query()
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('last_seen_at')
                ->paginate(15)
                ->withQueryString();

            $stats = $this->stats();

            return view('bug-reports::dashboard.index', [
                'activeStatus' => $status,
                'reports' => $reports,
                'statusCounts' => $stats['statusCounts'],
                'windowCounts' => $stats['windowCounts'],
                'topOrigins' => $stats['topOrigins'],
                'topExceptions' => $stats['topExceptions'],
                'totalReports' => $stats['statusCounts']['all'],
                'totalOccurrences' => $stats['totalOccurrences'],
                'slackInfo' => $this->slackInfo(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return view('bug-reports::dashboard.missing-migration', [
                'message' => 'Unable to load bug reports. Check the application logs for details.',
            ]);
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable((new BugReport)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Dashboard aggregates are expensive and tolerate brief staleness, so they
     * are cached for a short window and busted when a report changes.
     *
     * @return array{statusCounts: array<string, int>, windowCounts: array<int, int>, topOrigins: mixed, topExceptions: mixed, totalOccurrences: int}
     */
    private function stats(): array
    {
        return Cache::remember($this->statsCacheKey(), 60, fn (): array => [
            'statusCounts' => $this->statusCounts(),
            'windowCounts' => $this->windowCounts(),
            'topOrigins' => $this->topOrigins(),
            'topExceptions' => $this->topExceptions(),
            'totalOccurrences' => (int) BugReport::query()->sum('occurrences'),
        ]);
    }

    private function statsCacheKey(): string
    {
        return config('bug-reports.cache_prefix', 'bug-reports').':dashboard:stats';
    }

    public function solve(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::solve($bugReport->fingerprint);
        $this->forgetStats();

        return back()->with('bug_reports_status', 'Bug report marked as solved.');
    }

    public function ignore(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::ignore($bugReport->fingerprint);
        $this->forgetStats();

        return back()->with('bug_reports_status', 'Bug report ignored.');
    }

    public function delete(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::delete($bugReport->fingerprint);
        $this->forgetStats();

        return back()->with('bug_reports_status', 'Bug report deleted.');
    }

    private function forgetStats(): void
    {
        Cache::forget($this->statsCacheKey());
    }

    private function authorizeDashboard(Request $request): void
    {
        abort_unless($this->canView($request), 403);
    }

    private function canView(Request $request): bool
    {
        $user = $request->user();
        $allowedUserIds = config('bug-reports.dashboard.user_ids', []);

        if ($user && in_array((string) $user->getAuthIdentifier(), array_map('strval', $allowedUserIds), true)) {
            return true;
        }

        return Gate::allows(config('bug-reports.dashboard.gate', 'viewBugReports'));
    }

    /**
     * @return array<string, mixed>
     */
    private function slackInfo(): array
    {
        $token = config('bug-reports.slack.bot_token');

        return [
            'connected' => filled($token) && filled(config('bug-reports.slack.channel')),
            'channel' => config('bug-reports.slack.channel') ?: 'Not configured',
            'app_mode' => config('bug-reports.slack.app_mode', 'own') === 'managed' ? 'LaravelBugBot app' : 'Own Slack app',
            'username' => config('bug-reports.slack.username'),
            'emoji' => config('bug-reports.slack.emoji'),
            'level' => strtoupper((string) config('bug-reports.level', 'error')),
            'throttle_minutes' => (int) config('bug-reports.throttle_minutes', 5),
            'actions_enabled' => (bool) config('bug-reports.slack.actions.enabled', true),
            'log_channel' => config('logging.default'),
            'expected_channel' => config('bug-reports.channel', 'bug_reports'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = [
            'all' => BugReport::query()->count(),
            BugReport::STATUS_PENDING => 0,
            BugReport::STATUS_SOLVED => 0,
            BugReport::STATUS_IGNORED => 0,
        ];

        BugReport::query()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get()
            ->each(function ($row) use (&$counts): void {
                $counts[$row->status] = (int) $row->total;
            });

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function windowCounts(): array
    {
        $windows = [1, 5, 7, 10, 30];

        $query = BugReportOccurrence::query();

        foreach ($windows as $days) {
            $query->selectRaw(
                'sum(case when occurred_at >= ? then 1 else 0 end) as d'.$days,
                [now()->subDays($days)]
            );
        }

        $row = $query->first();

        return collect($windows)
            ->mapWithKeys(fn (int $days): array => [$days => (int) ($row?->{'d'.$days} ?? 0)])
            ->all();
    }

    private function topOrigins()
    {
        return BugReportOccurrence::query()
            ->select('origin')
            ->selectRaw('count(*) as total')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('origin')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    private function topExceptions()
    {
        return BugReportOccurrence::query()
            ->select('exception_class')
            ->selectRaw('count(*) as total')
            ->where('occurred_at', '>=', now()->subDays(30))
            ->groupBy('exception_class')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }
}
