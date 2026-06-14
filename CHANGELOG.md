# Changelog

All notable changes to `zereflab/laravel-bug-bot` will be documented in this file.

## 2.0.0 - 2026-06-13

Laravel Bug Reports 2.0.0 makes the package easier to install, safer to run in production, and clearer to operate after Slack is connected.

### Highlights

- Added the managed Laravel Bug Bot setup flow, including clear `.env` values for `LOG_CHANNEL`, `LOG_LEVEL`, managed Slack mode, bot token, channel ID, and Slack actions.
- Added step-by-step Slack channel setup documentation with screenshots showing how to add the bot to a channel and copy the Slack channel ID.
- Redesigned the dashboard with the LaravelBugBot dark branding.
- Added a Slack connection panel to the dashboard showing connection status, channel, app mode, reporter identity, minimum log level, throttle settings, Slack action status, and log-channel misconfiguration warnings.
- Improved setup guidance for the built-in dashboard, including the dashboard URL, gate-based access control, and optional user ID allowlisting.

### Breaking change

- The dashboard now denies access by default outside your explicit authorization rules. Define the `viewBugReports` gate in `app/Providers/AppServiceProvider.php`, or set `BUG_REPORTS_DASHBOARD_USER_IDS` to allow specific database user IDs.

### Upgrade notes

Add or confirm these values in your `.env` when using the managed Slack app:

```env
LOG_CHANNEL=bug_reports
LOG_LEVEL=error
BUG_REPORTS_SLACK_APP_MODE=managed
BUG_REPORTS_SLACK_BOT_TOKEN=xoxb-generated-token
BUG_REPORTS_SLACK_CHANNEL=C1234567890
BUG_REPORTS_SLACK_ACTIONS_ENABLED=true
```

Authorize dashboard users with a gate:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewBugReports', function (User $user) {
        return $user->is_admin;
    });
}
```

Or optionally allow specific database user IDs:

```env
BUG_REPORTS_DASHBOARD_USER_IDS=205,206
```

## 1.0.0 - 2026-06-12

Initial release.

### Slack reporting

- Adds the `bug_reports` Laravel log channel.
- Sends a concise Slack parent message with date, level, exception, message, origin, entity, and location.
- Sends the full exception, request details, context, and stack trace in the Slack thread.
- Throttles duplicate alerts by exception fingerprint (class, message, file, line).
- Adds Slack `Solved` and `Ignore` buttons with a signed action endpoint.
- Supports the pre-built LaravelBugBot Slack app or your own Slack app (`chat:write`, `chat:write.customize`).

### Persistence and dashboard

- Stores bug reports, occurrences, and solved/ignored state in the database via package migrations.
- Reopens solved errors as pending when they happen again; ignored fingerprints suppress future alerts.
- Adds a built-in dashboard at `/bugs-report` with status counts, 1/5/7/10/30 day analytics, noisiest origins, top exception classes, and paginated pending/resolved/ignored tables with resolve, ignore, and delete actions.
- Gate-based dashboard authorization (`viewBugReports`) with an optional env user ID allowlist.

### Tooling

- Adds the `bug-reports:test` setup command that fails loudly on Slack configuration issues.
