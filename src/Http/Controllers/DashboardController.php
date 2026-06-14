<?php

namespace Zereflab\LaravelBugReports\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
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

        try {
            $reports = BugReport::query()
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest('last_seen_at')
                ->paginate(15)
                ->withQueryString();

            return view('bug-reports::dashboard.index', [
                'activeStatus' => $status,
                'reports' => $reports,
                'statusCounts' => $this->statusCounts(),
                'windowCounts' => $this->windowCounts(),
                'topOrigins' => $this->topOrigins(),
                'topExceptions' => $this->topExceptions(),
                'totalReports' => BugReport::query()->count(),
                'totalOccurrences' => BugReport::query()->sum('occurrences'),
                'slackInfo' => $this->slackInfo(),
            ]);
        } catch (Throwable $exception) {
            return view('bug-reports::dashboard.missing-migration', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function solve(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::solve($bugReport->fingerprint);

        return back()->with('bug_reports_status', 'Bug report marked as solved.');
    }

    public function ignore(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::ignore($bugReport->fingerprint);

        return back()->with('bug_reports_status', 'Bug report ignored.');
    }

    public function delete(Request $request, BugReport $bugReport): RedirectResponse
    {
        $this->authorizeDashboard($request);

        ReportState::delete($bugReport->fingerprint);

        return back()->with('bug_reports_status', 'Bug report deleted.');
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
        return collect([1, 5, 7, 10, 30])
            ->mapWithKeys(fn (int $days): array => [
                $days => BugReportOccurrence::query()
                    ->where('occurred_at', '>=', now()->subDays($days))
                    ->count(),
            ])
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
