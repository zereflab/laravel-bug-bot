<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bug Reports — LaravelBugBot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0d14;
            --bg-soft: #11141f;
            --card: #161a28;
            --border: #232839;
            --text: #e7e9f0;
            --muted: #9aa1b5;
            --accent: #ff4757;
            --accent-2: #7c5cff;
            --green: #2ed573;
            --amber: #fbbf24;
            --radius: 16px;
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.6; margin: 0; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        .shell { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }

        .sidebar { background: var(--bg-soft); border-right: 1px solid var(--border); padding: 26px 18px; }
        .brand { align-items: center; display: flex; font-size: 17px; font-weight: 800; gap: 10px; margin-bottom: 30px; padding: 0 6px; }
        .brand svg { display: block; flex: none; }
        .brand .accent { color: var(--accent); }
        .nav a { align-items: center; border-radius: 10px; color: var(--muted); display: flex; font-size: 14px; font-weight: 600; justify-content: space-between; margin-bottom: 6px; padding: 11px 14px; }
        .nav a.active, .nav a:hover { background: var(--card); color: var(--text); }
        .nav a.active { border: 1px solid var(--border); }
        .nav .count { background: var(--bg); border: 1px solid var(--border); border-radius: 999px; color: var(--muted); font-size: 12px; font-weight: 700; padding: 2px 9px; }
        .nav a.active .count { color: var(--text); }
        .sidebar .foot { color: var(--muted); font-size: 12px; margin-top: 30px; padding: 0 6px; }
        .sidebar .foot a { color: var(--text); font-weight: 600; }

        .main { padding: 30px 34px; }
        .header { align-items: flex-start; display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; margin-bottom: 20px; }
        .header h1 { font-size: 26px; letter-spacing: -0.01em; margin: 0; }
        .muted { color: var(--muted); }

        .notice { background: rgba(46, 213, 115, .1); border: 1px solid rgba(46, 213, 115, .35); border-radius: 12px; color: var(--green); font-weight: 600; margin-bottom: 18px; padding: 12px 16px; }

        .slack-info { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); display: flex; flex-wrap: wrap; gap: 26px; margin-bottom: 18px; padding: 18px 22px; }
        .slack-info .item .label { color: var(--muted); font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .slack-info .item .value { font-size: 14.5px; font-weight: 700; margin-top: 3px; }
        .dot { border-radius: 999px; display: inline-block; height: 8px; margin-right: 7px; width: 8px; }
        .dot.on { background: var(--green); }
        .dot.off { background: var(--accent); }

        .grid { display: grid; gap: 14px; grid-template-columns: repeat(6, minmax(0, 1fr)); margin-bottom: 18px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; }
        .card .label { color: var(--muted); font-size: 12px; font-weight: 600; margin-bottom: 6px; }
        .card .value { font-size: 23px; font-weight: 800; letter-spacing: -0.01em; }

        .analytics { display: grid; gap: 14px; grid-template-columns: 1fr 1fr; margin-bottom: 18px; }
        .panel { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 22px; }
        .panel .label { color: var(--muted); font-size: 12px; font-weight: 700; letter-spacing: .05em; margin-bottom: 10px; text-transform: uppercase; }
        .list-row { align-items: center; display: flex; gap: 12px; justify-content: space-between; padding: 8px 0; }
        .list-row + .list-row { border-top: 1px solid var(--border); }
        .list-row .bar-track { background: var(--bg); border-radius: 999px; flex: 1; height: 6px; }
        .list-row .bar { background: linear-gradient(90deg, var(--accent), var(--accent-2)); border-radius: 999px; height: 6px; }
        .list-row span:first-child { max-width: 45%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid var(--border); font-size: 14px; padding: 13px 16px; text-align: left; vertical-align: top; }
        th { background: var(--bg-soft); color: var(--muted); font-size: 11.5px; letter-spacing: .05em; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        .message { font-weight: 700; max-width: 440px; }
        .sub { color: var(--muted); font-size: 12px; margin-top: 4px; word-break: break-all; }
        .badge { border-radius: 999px; display: inline-block; font-size: 11.5px; font-weight: 800; letter-spacing: .03em; padding: 4px 10px; text-transform: capitalize; }
        .badge.pending { background: rgba(251, 191, 36, .12); border: 1px solid rgba(251, 191, 36, .4); color: var(--amber); }
        .badge.solved { background: rgba(46, 213, 115, .12); border: 1px solid rgba(46, 213, 115, .4); color: var(--green); }
        .badge.ignored { background: rgba(255, 71, 87, .12); border: 1px solid rgba(255, 71, 87, .4); color: var(--accent); }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        button { border: 0; border-radius: 9px; cursor: pointer; font-family: inherit; font-size: 12.5px; font-weight: 700; padding: 8px 12px; transition: transform .12s ease; }
        button:hover { transform: translateY(-1px); }
        .btn-solve { background: rgba(46, 213, 115, .15); border: 1px solid rgba(46, 213, 115, .45); color: var(--green); }
        .btn-ignore { background: rgba(255, 71, 87, .12); border: 1px solid rgba(255, 71, 87, .45); color: var(--accent); }
        .btn-delete { background: var(--bg-soft); border: 1px solid var(--border); color: var(--muted); }

        .pagination { margin-top: 18px; }
        .pagination nav { color: var(--muted); }
        .pagination a, .pagination span { border-radius: 8px; padding: 5px 10px; }
        .pagination a:hover { background: var(--card); }

        @media (max-width: 1200px) { .grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 900px) { .shell { grid-template-columns: 1fr; } .sidebar { border-bottom: 1px solid var(--border); border-right: 0; } .analytics { grid-template-columns: 1fr; } .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <svg width="30" height="30" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="LaravelBugBot logo">
                    <defs>
                        <linearGradient id="lbb-grad" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#FF4757"/>
                            <stop offset="1" stop-color="#7C5CFF"/>
                        </linearGradient>
                    </defs>
                    <rect x="2" y="2" width="60" height="60" rx="16" fill="url(#lbb-grad)"/>
                    <ellipse cx="32" cy="36" rx="11" ry="13" fill="#0b0d14"/>
                    <circle cx="32" cy="20" r="6.5" fill="#0b0d14"/>
                    <path d="M28 15 L24 10 M36 15 L40 10" stroke="#0b0d14" stroke-width="3" stroke-linecap="round"/>
                    <path d="M21 30 L13 26 M21 38 L12 38 M21 45 L14 50 M43 30 L51 26 M43 38 L52 38 M43 45 L50 50" stroke="#0b0d14" stroke-width="3" stroke-linecap="round"/>
                    <path d="M32 26 V48" stroke="url(#lbb-grad)" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <span>Laravel<span class="accent">Bug</span>Bot</span>
            </div>
            <nav class="nav">
                @foreach (['all' => 'All Errors', 'pending' => 'Pending', 'solved' => 'Resolved', 'ignored' => 'Ignored'] as $status => $label)
                    <a class="{{ $activeStatus === $status ? 'active' : '' }}" href="{{ $status === 'all' ? route('bug-reports.dashboard') : route('bug-reports.dashboard.status', $status) }}">
                        <span>{{ $label }}</span>
                        <span class="count">{{ number_format($statusCounts[$status] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="foot">
                Powered by <a href="https://laravelbugbot.com" target="_blank" rel="noopener">laravelbugbot.com</a>
            </div>
        </aside>

        <main class="main">
            <div class="header">
                <div>
                    <h1>{{ $activeStatus === 'all' ? 'All bug reports' : ucfirst($activeStatus).' bug reports' }}</h1>
                    <div class="muted">Laravel exceptions reported to Slack, organized.</div>
                </div>
                <div class="muted">Total occurrences: <strong style="color: var(--text)">{{ number_format($totalOccurrences) }}</strong></div>
            </div>

            @if (session('bug_reports_status'))
                <div class="notice">{{ session('bug_reports_status') }}</div>
            @endif

            <section class="slack-info">
                <div class="item">
                    <div class="label">Slack</div>
                    <div class="value">
                        <span class="dot {{ $slackInfo['connected'] ? 'on' : 'off' }}"></span>{{ $slackInfo['connected'] ? 'Connected' : 'Not connected' }}
                    </div>
                </div>
                <div class="item">
                    <div class="label">Channel</div>
                    <div class="value">{{ $slackInfo['channel'] }}</div>
                </div>
                <div class="item">
                    <div class="label">App</div>
                    <div class="value">{{ $slackInfo['app_mode'] }}</div>
                </div>
                <div class="item">
                    <div class="label">Reports as</div>
                    <div class="value">{{ $slackInfo['emoji'] }} {{ $slackInfo['username'] }}</div>
                </div>
                <div class="item">
                    <div class="label">Min level</div>
                    <div class="value">{{ $slackInfo['level'] }}</div>
                </div>
                <div class="item">
                    <div class="label">Throttle</div>
                    <div class="value">{{ $slackInfo['throttle_minutes'] }} min</div>
                </div>
                <div class="item">
                    <div class="label">Slack buttons</div>
                    <div class="value">
                        <span class="dot {{ $slackInfo['actions_enabled'] ? 'on' : 'off' }}"></span>{{ $slackInfo['actions_enabled'] ? 'Enabled' : 'Disabled' }}
                    </div>
                </div>
                @if ($slackInfo['log_channel'] !== $slackInfo['expected_channel'])
                    <div class="item">
                        <div class="label">Log channel</div>
                        <div class="value"><span class="dot off"></span>{{ $slackInfo['log_channel'] }} (expected {{ $slackInfo['expected_channel'] }})</div>
                    </div>
                @endif
            </section>

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
                <div class="panel">
                    <div class="label">Noisiest origins · last 30 days</div>
                    @php($originMax = max(1, (int) ($topOrigins->max('total') ?? 1)))
                    @forelse ($topOrigins as $origin)
                        <div class="list-row">
                            <span title="{{ $origin->origin }}">{{ $origin->origin ?: 'Unknown origin' }}</span>
                            <span class="bar-track"><span class="bar" style="display:block; width: {{ (int) round($origin->total / $originMax * 100) }}%"></span></span>
                            <strong>{{ number_format($origin->total) }}</strong>
                        </div>
                    @empty
                        <div class="muted">No origin data yet.</div>
                    @endforelse
                </div>
                <div class="panel">
                    <div class="label">Top exception classes · last 30 days</div>
                    @php($exceptionMax = max(1, (int) ($topExceptions->max('total') ?? 1)))
                    @forelse ($topExceptions as $exception)
                        <div class="list-row">
                            <span title="{{ $exception->exception_class }}">{{ $exception->exception_class ? class_basename($exception->exception_class) : 'Log message' }}</span>
                            <span class="bar-track"><span class="bar" style="display:block; width: {{ (int) round($exception->total / $exceptionMax * 100) }}%"></span></span>
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
