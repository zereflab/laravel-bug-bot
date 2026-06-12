# Use Your Own Slack App

The fastest way to use Laravel Bug Reports is the pre-built LaravelBugBot Slack app — see the [main README](../README.md), or install it directly:

<a href="https://laravelbugbot.com/integrations/slack/install" target="_blank"><img alt="Add to Slack" height="40" width="139" src="https://platform.slack-edge.com/img/add_to_slack.png" srcset="https://platform.slack-edge.com/img/add_to_slack.png 1x, https://platform.slack-edge.com/img/add_to_slack@2x.png 2x"></a>

Use this guide instead if you want full control with your own Slack app. This is also currently the only way to get the Slack `Solved` / `Ignore` buttons, since button clicks are delivered to the Slack app owner.

## 1. Create the Slack app

1. Go to <https://api.slack.com/apps> and click **Create New App** → **From scratch**.
2. Name it (for example, your app's name) and pick your workspace.

## 2. Add bot token scopes

Under **OAuth & Permissions → Bot Token Scopes**, add:

| Scope | Why |
| --- | --- |
| `chat:write` | Post parent alerts, thread replies, and update messages on Solved/Ignore. |
| `chat:write.customize` | Only needed if you customize the alert username or emoji icon. |

No admin scopes are required. Do not grant the app workspace admin permissions.

## 3. Install the app to your workspace

On the **OAuth & Permissions** page, click **Install to Workspace** and authorize. Copy the **Bot User OAuth Token** (`xoxb-…`).

## 4. Invite the bot to your channel

In the target Slack channel:

```text
/invite @your-bot-name
```

For private channels, the bot must be invited as well.

## 5. Configure your .env

```env
LOG_CHANNEL=bug_reports

BUG_REPORTS_SLACK_APP_MODE=own
BUG_REPORTS_SLACK_BOT_TOKEN=xoxb-your-token
BUG_REPORTS_SLACK_CHANNEL=C1234567890
BUG_REPORTS_SLACK_SIGNING_SECRET=your-signing-secret
```

- The channel value is the channel **ID** (starts with `C`), not the name. Find it at the bottom of the channel's About tab.
- The signing secret is on the app's **Basic Information** page under App Credentials.

## 6. Enable interactivity for the Solved / Ignore buttons

1. In the Slack app settings, open **Interactivity & Shortcuts**.
2. Toggle **Interactivity** to On.
3. Set the **Request URL** to your application:

```text
https://your-domain.com/bug-reports/slack/actions
```

If you changed `BUG_REPORTS_ROUTE_PREFIX`, adjust the URL accordingly. Your application must be publicly reachable at that URL.

Keep the buttons enabled in your `.env`:

```env
BUG_REPORTS_SLACK_ACTIONS_ENABLED=true
```

## 7. Test your setup

```bash
php artisan bug-reports:test
php artisan bug-reports:test --message="Production Slack test"
```

The command uses the real package log channel and throws Slack API failures loudly, so configuration problems surface immediately.

## Troubleshooting

| Error | Fix |
| --- | --- |
| `channel_not_found` | Use the channel ID, not the name; invite the bot to private channels. |
| `not_in_channel` | Invite the bot to the channel with `/invite`. |
| `invalid_auth` | Token is wrong, truncated, or from another workspace. |
| `missing_scope` | Add the scope, then **reinstall the app to your workspace** — scope changes only apply after reinstalling. |
| Buttons do nothing | Interactivity is off, the Request URL is wrong/unreachable, or the signing secret doesn't match. |
