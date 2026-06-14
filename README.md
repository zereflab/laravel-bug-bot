<p align="center">
    <a href="https://laravelbugbot.com">
        <img src="https://laravelbugbot.com/images/logo.png" alt="Laravel Bug Bot" width="360">
    </a>
</p>

<p align="center">
    <a href="https://packagist.org/packages/zereflab/laravel-bug-bot"><img alt="Packagist Version" src="https://img.shields.io/packagist/v/zereflab/laravel-bug-bot?style=for-the-badge"></a>
    <a href="https://packagist.org/packages/zereflab/laravel-bug-bot"><img alt="Packagist Downloads" src="https://img.shields.io/packagist/dt/zereflab/laravel-bug-bot?style=for-the-badge"></a>
    <a href="LICENSE.md"><img alt="License" src="https://img.shields.io/badge/license-MIT-2ed573?style=for-the-badge"></a>
</p>

# Laravel Bug Bot

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

## Getting Started

### 1. Install the package

```bash
composer require zereflab/laravel-bug-bot
```

### 2. Publish the config

```bash
php artisan vendor:publish --tag=bug-reports-config
```

### 3. Run the migrations

```bash
php artisan migrate
```

### 4. Connect Slack

Install the pre-built **Laravel Bug Bot** Slack app into your workspace — no Slack app creation needed:

<p>
    <a href="https://laravelbugbot.com/integrations/slack/install">
        <img alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x">
    </a>
</p>

Direct install URL: <https://laravelbugbot.com/integrations/slack/install>

After installation, the success page shows a managed bot token. Paste the generated values into your `.env`:

```env
LOG_CHANNEL=bug_reports
LOG_LEVEL=error

BUG_REPORTS_SLACK_APP_MODE=managed
BUG_REPORTS_SLACK_BOT_TOKEN=xoxb-generated-token
BUG_REPORTS_SLACK_CHANNEL=C1234567890
BUG_REPORTS_SLACK_ACTIONS_ENABLED=true
```

> Managed Slack installs use `laravelbugbot.com` as the Slack Interactivity Request URL. The package embeds a signed callback in each button so Laravel Bug Bot can forward `Solved` / `Ignore` clicks back to the Laravel app where this package is installed.

### 5. Add Laravel Bug Bot to your Slack channel

Slack requires you to add the installed app to the channel before it can post alerts there.

<table>
    <tr>
        <td width="50%">
            <strong>1. Click the channel name</strong><br>
            Open your Slack channel and click the channel name at the top.
            <br><br>
            <img src="docs/images/slack-install/01-click-channel-name.png" alt="Click the Slack channel name">
        </td>
        <td width="50%">
            <strong>2. Open Integrations</strong><br>
            In the channel details modal, open the <strong>Integrations</strong> tab.
            <br><br>
            <img src="docs/images/slack-install/02-open-integrations.png" alt="Open the Integrations tab">
        </td>
    </tr>
    <tr>
        <td width="50%">
            <strong>3. Click Add an App</strong><br>
            In the Apps section, click <strong>Add an App</strong>.
            <br><br>
            <img src="docs/images/slack-install/03-add-app.png" alt="Click Add an App">
        </td>
        <td width="50%">
            <strong>4. Add Laravel Bug Bot</strong><br>
            Find <strong>Laravel Bug Bot</strong> and click <strong>Add</strong>.
            <br><br>
            <img src="docs/images/slack-install/04-add-laravel-bug-bot.png" alt="Add Laravel Bug Bot to Slack">
        </td>
    </tr>
</table>

### 6. Copy the Slack channel ID

Copy the Channel ID from Slack, then replace the placeholder in `BUG_REPORTS_SLACK_CHANNEL`.

<p>
    <img src="docs/images/slack-install/05-copy-channel-id.png" alt="Copy the Channel ID from Slack">
</p>

The channel value is the channel **ID**, not the channel name.

### 7. Test your setup

```bash
php artisan bug-reports:test
```

You should see the test exception arrive in your Slack channel. That's it — every exception in your app now lands in Slack, organized.

### 8. Authorize dashboard access

The dashboard is served by the Laravel application where you install this package:

```text
/bugs-report
```

Full dashboard documentation is available at <https://laravelbugbot.com/docs/dashboard>.

Edit `app/Providers/AppServiceProvider.php` and add the dashboard gate inside the `boot()` function:

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

Optionally, allow specific users by database ID:

```env
BUG_REPORTS_DASHBOARD_USER_IDS=205,206
```

## Want To Use Your Own Slack Application?

If you prefer to create and control your own Slack app instead of using the managed Laravel Bug Bot app, follow the separate guide:

**[➡ Use Your Own Slack App](docs/use-your-own-slack-app.md)**

## Environment Variables

Defaults shown below — override only what you need:

```env
BUG_REPORTS_REPORTER=slack
BUG_REPORTS_LOG_CHANNEL=bug_reports
BUG_REPORTS_CACHE_PREFIX=bug-reports
BUG_REPORTS_SLACK_APP_MODE=own
BUG_REPORTS_SLACK_INSTALL_URL=https://laravelbugbot.com/integrations/slack/install
BUG_REPORTS_SLACK_SIGNING_SECRET=
BUG_REPORTS_SLACK_USERNAME="${APP_NAME}"
BUG_REPORTS_SLACK_EMOJI=:boom:
BUG_REPORTS_SLACK_ACTIONS_ENABLED=true
BUG_REPORTS_MANAGED_ACTION_URL=
BUG_REPORTS_MANAGED_ACTION_SECRET=
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
- In managed Slack app mode, button clicks go to `laravelbugbot.com` first, then get forwarded to a signed callback endpoint in your Laravel app.
- In own Slack app mode, Slack can post directly to your Laravel app's signed action endpoint.
- Both actions update stored report state for that same fingerprint.
- If a solved fingerprint happens again, it is reopened as pending.

Bug reports, occurrence counts, statuses, and Slack message references are stored in the database. By default, ignored errors are ignored forever. You can expire ignored errors:

```env
BUG_REPORTS_SLACK_IGNORE_TTL_DAYS=30
```

### Slack button endpoint

The package automatically registers the Slack interactivity endpoint:

```text
POST /bug-reports/slack/actions
```

By default this route uses the `api` middleware and is also registered without Laravel's CSRF middleware, because Slack cannot send a Laravel CSRF token. You should not add this route manually.

To avoid 404s from the common dashboard/action URL mismatch, the package also registers compatibility aliases:

```text
POST /bugs-report/slack/actions
POST /bug-report/slack/actions
POST /bugs-reports/slack/actions
POST /{BUG_REPORTS_DASHBOARD_PATH}/slack/actions
```

If Slack reaches Laravel but `BUG_REPORTS_SLACK_SIGNING_SECRET` is missing or wrong, the endpoint returns `401 Invalid signature` instead of `404`.

Managed Slack app installs also automatically register this client callback endpoint:

```text
POST /bug-reports/managed/actions
```

Laravel Bug Bot signs each Slack button value with `BUG_REPORTS_MANAGED_ACTION_SECRET` (defaults to `APP_KEY`) and includes this callback URL. When Slack sends the click to `laravelbugbot.com`, Laravel Bug Bot forwards the action to this endpoint so the package can update the local database.

Make sure `APP_URL` is correct in the Laravel app where the package is installed. If auto-detection is wrong, set:

```env
BUG_REPORTS_MANAGED_ACTION_URL=https://your-app.com/bug-reports/managed/actions
```

If you use your own Slack app and want Slack `Solved` / `Ignore` buttons, set the Slack app's **Interactivity Request URL** to your deployed Laravel app:

```text
https://<your-laravel-app-domain>/bug-reports/slack/actions
```

If you change `BUG_REPORTS_ROUTE_PREFIX`, the URL changes too. For example:

```env
BUG_REPORTS_ROUTE_PREFIX=internal/bug-reports
```

Then Slack must post to:

```text
https://<your-laravel-app-domain>/internal/bug-reports/slack/actions
```

## Dashboard

The package includes a built-in dashboard served inside your Laravel application:

```text
/bugs-report
```

Read the full dashboard guide at <https://laravelbugbot.com/docs/dashboard>.

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

### Dashboard authorization

**The dashboard denies everyone by default.** To grant access, edit `app/Providers/AppServiceProvider.php` and add the gate inside the `boot()` function:

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

Or allow specific user IDs without defining a gate:

```env
BUG_REPORTS_DASHBOARD_USER_IDS=1,42
```

Add authentication middleware so visitors are sent to login first:

```env
BUG_REPORTS_DASHBOARD_MIDDLEWARE=web,auth
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

