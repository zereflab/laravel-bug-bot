# Changelog

All notable changes to `zereflab/laravel-bug-reports` will be documented in this file.

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
- Adds a Horizon-style dashboard at `/bugs-report` with status counts, 1/5/7/10/30 day analytics, noisiest origins, top exception classes, and paginated pending/resolved/ignored tables with resolve, ignore, and delete actions.
- Gate-based dashboard authorization (`viewBugReports`) with an optional env user ID allowlist.

### Tooling

- Adds the `bug-reports:test` setup command that fails loudly on Slack configuration issues.
