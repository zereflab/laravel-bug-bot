<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bug Reports</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        body { margin: 0; background: #f6f7fb; color: #111827; }
        a { color: inherit; text-decoration: none; }
        .shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; }
        .sidebar { background: #111827; color: #d1d5db; padding: 28px 20px; }
        .brand { color: #fff; font-size: 20px; font-weight: 700; margin-bottom: 28px; }
        .nav a { align-items: center; border-radius: 10px; display: flex; justify-content: space-between; margin-bottom: 8px; padding: 11px 12px; }
        .nav a.active, .nav a:hover { background: #1f2937; color: #fff; }
        .nav span:last-child { background: #374151; border-radius: 999px; color: #fff; font-size: 12px; padding: 2px 8px; }
        .main { padding: 32px; }
        .header { align-items: center; display: flex; justify-content: space-between; margin-bottom: 22px; }
        .header h1 { font-size: 28px; margin: 0; }
        .muted { color: #6b7280; }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); padding: 18px; }
        .card .label { color: #6b7280; font-size: 13px; margin-bottom: 8px; }
        .card .value { font-size: 26px; font-weight: 700; }
        .analytics { display: grid; gap: 16px; grid-template-columns: 1fr 1fr; margin-bottom: 18px; }
        .list-row { align-items: center; display: flex; justify-content: space-between; padding: 9px 0; }
        .list-row + .list-row { border-top: 1px solid #f3f4f6; }
        .table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #f3f4f6; font-size: 14px; padding: 14px 16px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; color: #6b7280; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        .message { font-weight: 600; max-width: 460px; }
        .sub { color: #6b7280; font-size: 12px; margin-top: 5px; }
        .badge { border-radius: 999px; display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 9px; text-transform: capitalize; }
        .badge.pending { background: #fef3c7; color: #92400e; }
        .badge.solved { background: #dcfce7; color: #166534; }
        .badge.ignored { background: #fee2e2; color: #991b1b; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        button { border: 0; border-radius: 9px; cursor: pointer; font-weight: 700; padding: 8px 10px; }
        .btn-solve { background: #16a34a; color: #fff; }
        .btn-ignore { background: #dc2626; color: #fff; }
        .btn-delete { background: #e5e7eb; color: #111827; }
        .notice { background: #ecfdf5; border: 1px solid #bbf7d0; border-radius: 12px; color: #166534; margin-bottom: 18px; padding: 12px 14px; }
        .pagination { margin-top: 18px; }
        @media (max-width: 1100px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .analytics { grid-template-columns: 1fr; } .shell { grid-template-columns: 1fr; } .sidebar { position: static; } }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">Bug Reports</div>
            <nav class="nav">
                @foreach (['all' => 'All Errors', 'pending' => 'Pending', 'solved' => 'Resolved', 'ignored' => 'Ignored'] as $status => $label)
                    <a class="{{ $activeStatus === $status ? 'active' : '' }}" href="{{ $status === 'all' ? route('bug-reports.dashboard') : route('bug-reports.dashboard.status', $status) }}">
                        <span>{{ $label }}</span>
                        <span>{{ number_format($statusCounts[$status] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="main">
            <div class="header">
                <div>
                    <h1>{{ ucfirst($activeStatus === 'all' ? 'all bug reports' : $activeStatus.' bug reports') }}</h1>
                    <div class="muted">Track Laravel exceptions reported to Slack.</div>
                </div>
                <div class="muted">Total occurrences: {{ number_format($totalOccurrences) }}</div>
            </div>

            @if (session('bug_reports_status'))
                <div class="notice">{{ session('bug_reports_status') }}</div>
            @endif

            <section class="grid">
                <div class="card">
                    <div class="label">Error fingerprints</div>
                    <div class="value">{{ number_format($totalReports) }}</div>
                </div>
                @foreach ($windowCounts as $days => $count)
                    <div class="card">
                        <div class="label">Last {{ $days }} {{ $days === 1 ? 'day' : 'days' }}</div>
                        <div class="value">{{ number_format($count) }}</div>
                    </div>
                @endforeach
            </section>

            <section class="analytics">
                <div class="card">
                    <div class="label">Noisiest origins, last 30 days</div>
                    @forelse ($topOrigins as $origin)
                        <div class="list-row">
                            <span>{{ $origin->origin ?: 'Unknown origin' }}</span>
                            <strong>{{ number_format($origin->total) }}</strong>
                        </div>
                    @empty
                        <div class="muted">No origin data yet.</div>
                    @endforelse
                </div>
                <div class="card">
                    <div class="label">Top exception classes, last 30 days</div>
                    @forelse ($topExceptions as $exception)
                        <div class="list-row">
                            <span>{{ $exception->exception_class ? class_basename($exception->exception_class) : 'Log message' }}</span>
                            <strong>{{ number_format($exception->total) }}</strong>
                        </div>
                    @empty
                        <div class="muted">No exception data yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Error</th>
                            <th>Status</th>
                            <th>Origin</th>
                            <th>Occurrences</th>
                            <th>Last seen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr>
                                <td>
                                    <div class="message">{{ $report->message ?: '(empty message)' }}</div>
                                    <div class="sub">
                                        {{ $report->exception_class ? class_basename($report->exception_class) : 'Log message' }}
                                        @if ($report->file)
                                            · {{ $report->file }}{{ $report->line ? ':'.$report->line : '' }}
                                        @endif
                                    </div>
                                    <div class="sub">Fingerprint: {{ $report->fingerprint }}</div>
                                </td>
                                <td><span class="badge {{ $report->status }}">{{ $report->status }}</span></td>
                                <td>
                                    {{ $report->origin ?: 'Unknown' }}
                                    @if ($report->entity)
                                        <div class="sub">{{ $report->entity }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format($report->occurrences) }}</td>
                                <td>
                                    {{ $report->last_seen_at?->diffForHumans() ?: 'Unknown' }}
                                    <div class="sub">{{ $report->last_seen_at?->toDayDateTimeString() }}</div>
                                </td>
                                <td>
                                    <div class="actions">
                                        @if ($report->status !== 'solved')
                                            <form method="post" action="{{ route('bug-reports.dashboard.solve', $report) }}">
                                                @csrf
                                                <button class="btn-solve" type="submit">Resolve</button>
                                            </form>
                                        @endif
                                        @if ($report->status !== 'ignored')
                                            <form method="post" action="{{ route('bug-reports.dashboard.ignore', $report) }}">
                                                @csrf
                                                <button class="btn-ignore" type="submit">Ignore</button>
                                            </form>
                                        @endif
                                        <form method="post" action="{{ route('bug-reports.dashboard.delete', $report) }}">
                                            @csrf
                                            @method('delete')
                                            <button class="btn-delete" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="muted">No bug reports found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <div class="pagination">{{ $reports->links() }}</div>
        </main>
    </div>
</body>
</html>
