# Laravel Bug Reports

Organized Laravel exception reports for Slack.

This package replaces noisy default Slack log messages with a clean parent alert, a full threaded exception, duplicate throttling, and Slack buttons for `Solved` / `Ignore`.

## Features

- Laravel log channel integration
- Short parent Slack message with useful context
- Full exception and stack trace in the Slack thread
- Duplicate throttling by exception fingerprint
- `Solved` and `Ignore` Slack buttons
- One click updates every stored Slack message for the same error fingerprint
- Ignored fingerprints suppress future alerts
- Safe production behavior: Slack failures do not break your app
- Test command that fails loudly when Slack config is wrong
- Designed for future Discord support

## Installation

```bash
composer require zereflab/laravel-bug-reports
```

Publish the config:

```bash
php artisan vendor:publish --tag=bug-reports-config
```

Run the migrations:

```bash
php artisan migrate
```

Set Laravel to use the package log channel:

```env
LOG_CHANNEL=bug_reports
```

## Environment Variables

Minimum Slack setup:

```env
LOG_CHANNEL=bug_reports

BUG_REPORTS_SLACK_BOT_TOKEN=xoxb-your-token
BUG_REPORTS_SLACK_CHANNEL=C1234567890
BUG_REPORTS_SLACK_SIGNING_SECRET=your-signing-secret

BUG_REPORTS_LEVEL=error
BUG_REPORTS_THROTTLE_MINUTES=5
```

Optional:

```env
BUG_REPORTS_REPORTER=slack
BUG_REPORTS_LOG_CHANNEL=bug_reports
BUG_REPORTS_CACHE_PREFIX=bug-reports
BUG_REPORTS_SLACK_APP_MODE=own
BUG_REPORTS_SLACK_INSTALL_URL=https://laravelbugbot.com/integrations/slack/install
BUG_REPORTS_SLACK_USERNAME="${APP_NAME}"
BUG_REPORTS_SLACK_EMOJI=:boom:
BUG_REPORTS_SLACK_ACTIONS_ENABLED=true
BUG_REPORTS_SLACK_IGNORE_TTL_DAYS=0
BUG_REPORTS_SLACK_SOLVED_TTL_DAYS=7
BUG_REPORTS_SLACK_STORED_MESSAGES=50
BUG_REPORTS_ROUTE_PREFIX=bug-reports
BUG_REPORTS_ROUTE_MIDDLEWARE=api
BUG_REPORTS_DASHBOARD_PATH=bugs-report
BUG_REPORTS_DASHBOARD_MIDDLEWARE=web,auth
BUG_REPORTS_DASHBOARD_GATE=viewBugReports
BUG_REPORTS_DASHBOARD_USER_IDS=
```

After changing config:

```bash
php artisan config:clear
php artisan config:cache
```

## Slack App Setup

You can connect Slack two ways: install our pre-built Slack app (fastest), or create your own.

### Option A: Install Our Pre-Built Slack App (Recommended)

No Slack app creation needed. Install the LaravelBugBot Slack app into your workspace:

**[➡ Add to Slack](https://laravelbugbot.com/integrations/slack/install)**

```text
https://laravelbugbot.com/integrations/slack/install
```

Or use the direct Slack authorization link:

```text
https://slack.com/oauth/v2/authorize?client_id=11349090337284.11339140816003&scope=chat:write,chat:write.customize&user_scope=
```

After authorizing, you'll see your bot token and workspace details. Paste them into your `.env`:

```env
BUG_REPORTS_SLACK_APP_MODE=managed
BUG_REPORTS_SLACK_BOT_TOKEN=xoxb-generated-token
BUG_REPORTS_SLACK_CHANNEL=C1234567890
```

Then invite the bot to your channel in Slack:

```text
/invite @LaravelBugBot
```

> **Note:** With the pre-built app, the Slack `Solved` / `Ignore` buttons are not yet supported, because button clicks are delivered to the app owner, not to your application. Disable them with `BUG_REPORTS_SLACK_ACTIONS_ENABLED=false` and manage statuses from the built-in dashboard instead. Bring your own Slack app (Option B) if you want the buttons today.

### Option B: Bring Your Own Slack App

1. Create an app at <https://api.slack.com/apps>.
2. Add bot token scopes:
   - `chat:write`
   - `chat:write.customize` (only if you customize the alert username/emoji)
3. Install the app to your workspace.
4. Invite the bot to the target channel.
5. Copy the bot token into `BUG_REPORTS_SLACK_BOT_TOKEN`.
6. Copy the channel ID into `BUG_REPORTS_SLACK_CHANNEL`.
7. Copy the signing secret into `BUG_REPORTS_SLACK_SIGNING_SECRET`.

## Slack Interactivity (Own App Only)

For the `Solved` / `Ignore` buttons, enable Interactivity in your Slack app and set the Request URL to your application:

```text
https://your-domain.com/bug-reports/slack/actions
```

If you changed `BUG_REPORTS_ROUTE_PREFIX`, update the URL accordingly.

## Test Your Setup

```bash
php artisan bug-reports:test
```

Custom message:

```bash
php artisan bug-reports:test --message="Production Slack test"
```

The command uses the real package log channel. Unlike production logging, it throws Slack API failures so you can fix config issues quickly.

## What The Slack Alert Looks Like

Parent message:

- Date/time
- Log level
- Exception class
- Exact exception message
- Origin: command, job, controller, route action, or application class
- Entity/user/model information when detectable
- File and line
- `Solved` and `Ignore` buttons

Thread reply:

- Exception class
- Message
- Location
- Level
- Environment
- Request details when available
- Context
- Stack trace
- Previous exception when available

## Solved And Ignore

Each exception gets a fingerprint based on exception class, message, file, and line.

- `Ignore` suppresses future alerts for that same fingerprint.
- `Solved` marks the fingerprint as resolved and clears the throttle.
- Both actions update all stored parent messages for that same fingerprint.
- If a solved fingerprint happens again, it is reopened as pending.

Bug reports, occurrence counts, statuses, and Slack message references are stored in the database. By default, ignored errors are ignored forever. You can expire ignored errors:

```env
BUG_REPORTS_SLACK_IGNORE_TTL_DAYS=30
```

## Dashboard

The package includes a Horizon-style dashboard:

```text
https://your-domain.com/bugs-report
```

The dashboard shows:

- Total error fingerprints and total occurrences
- Errors received in the last 1, 5, 7, 10, and 30 days
- Pending, resolved, and ignored counts
- Noisiest origins and exception classes
- Paginated tables for all, pending, resolved, and ignored errors
- Resolve, ignore, and delete actions

Change the dashboard path:

```env
BUG_REPORTS_DASHBOARD_PATH=internal/bugs
```

Use Laravel middleware to protect it:

```env
BUG_REPORTS_DASHBOARD_MIDDLEWARE=web,auth
```

Authorize access with a gate:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewBugReports', function (User $user) {
    return $user->is_admin;
});
```

Optionally allow specific user IDs to bypass the gate:

```env
BUG_REPORTS_DASHBOARD_USER_IDS=1,42
```

## Production Notes

Use this in production:

```env
APP_DEBUG=false
LOG_CHANNEL=bug_reports
BUG_REPORTS_LEVEL=error
BUG_REPORTS_THROTTLE_MINUTES=5
```

Do not use Laravel's default `slack` channel if you want this package's formatting. Use `bug_reports`.

## Future Discord Support

The package is structured around reporter drivers. Slack is the first reporter. Discord can be added later with the same fingerprinting, summary formatting, threaded/detail behavior where supported, and solved/ignore state.

## License

MIT

