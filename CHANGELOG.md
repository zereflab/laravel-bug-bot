# Changelog

All notable changes to `zereflab/laravel-bug-reports` will be documented in this file.

## 0.1.0 - Unreleased

- Initial Slack reporter.
- Adds `bug_reports` Laravel log channel.
- Sends a concise Slack parent message and full exception in the thread.
- Adds duplicate throttling by exception fingerprint.
- Adds Slack `Solved` and `Ignore` buttons.
- Adds signed Slack action endpoint.
- Adds `bug-reports:test` setup command.
