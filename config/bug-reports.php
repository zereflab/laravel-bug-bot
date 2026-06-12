<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Reporter
    |--------------------------------------------------------------------------
    |
    | Slack is the first supported reporter. The package is structured so more
    | reporters, such as Discord, can be added without changing application
    | logging setup.
    |
    */

    'default' => env('BUG_REPORTS_REPORTER', 'slack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Set LOG_CHANNEL to this value to send Laravel exceptions through the
    | package formatter instead of Laravel's default Slack formatter.
    |
    */

    'channel' => env('BUG_REPORTS_LOG_CHANNEL', 'bug_reports'),

    'level' => env('BUG_REPORTS_LEVEL', 'error'),

    'throttle_minutes' => (int) env('BUG_REPORTS_THROTTLE_MINUTES', 5),

    'cache_prefix' => env('BUG_REPORTS_CACHE_PREFIX', 'bug-reports'),

    'database' => [
        'table' => env('BUG_REPORTS_TABLE', 'bug_reports'),
    ],

    'routes' => [
        'enabled' => env('BUG_REPORTS_ROUTES_ENABLED', true),
        'prefix' => env('BUG_REPORTS_ROUTE_PREFIX', 'bug-reports'),
        'middleware' => array_filter(explode(',', (string) env('BUG_REPORTS_ROUTE_MIDDLEWARE', 'api'))),
    ],

    'dashboard' => [
        'enabled' => env('BUG_REPORTS_DASHBOARD_ENABLED', true),
        'path' => env('BUG_REPORTS_DASHBOARD_PATH', 'bugs-report'),
        'middleware' => array_filter(explode(',', (string) env('BUG_REPORTS_DASHBOARD_MIDDLEWARE', 'web'))),
        'gate' => env('BUG_REPORTS_DASHBOARD_GATE', 'viewBugReports'),
        'user_ids' => array_filter(array_map(
            static fn (string $id): string => trim($id),
            explode(',', (string) env('BUG_REPORTS_DASHBOARD_USER_IDS', ''))
        )),
    ],

    'slack' => [
        /*
        |--------------------------------------------------------------------------
        | Slack App Mode
        |--------------------------------------------------------------------------
        |
        | Use "own" when the project owner creates their own Slack app.
        | Use "managed" when they install your public Slack app and paste the
        | token/channel/signing secret generated for that installation.
        |
        */

        'app_mode' => env('BUG_REPORTS_SLACK_APP_MODE', 'own'),

        /*
        |--------------------------------------------------------------------------
        | Managed App Install URL
        |--------------------------------------------------------------------------
        |
        | Users who do not want to create their own Slack app can install the
        | pre-built LaravelBugBot Slack app by visiting this URL. After
        | authorizing, they receive the bot token and channel values to paste
        | into their .env file.
        |
        */

        'managed_install_url' => env(
            'BUG_REPORTS_SLACK_INSTALL_URL',
            'https://laravelbugbot.com/integrations/slack/install'
        ),

        'bot_token' => env('BUG_REPORTS_SLACK_BOT_TOKEN', env('SLACK_BOT_USER_OAUTH_TOKEN')),
        'channel' => env('BUG_REPORTS_SLACK_CHANNEL', env('LOG_SLACK_CHANNEL', env('SLACK_BOT_USER_DEFAULT_CHANNEL'))),
        'signing_secret' => env('BUG_REPORTS_SLACK_SIGNING_SECRET', env('SLACK_SIGNING_SECRET')),
        'username' => env('BUG_REPORTS_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
        'emoji' => env('BUG_REPORTS_SLACK_EMOJI', ':boom:'),

        'actions' => [
            'enabled' => env('BUG_REPORTS_SLACK_ACTIONS_ENABLED', true),
            'ignore_ttl_days' => (int) env('BUG_REPORTS_SLACK_IGNORE_TTL_DAYS', 0),
            'solved_ttl_days' => (int) env('BUG_REPORTS_SLACK_SOLVED_TTL_DAYS', 7),
            'stored_messages' => (int) env('BUG_REPORTS_SLACK_STORED_MESSAGES', 50),
        ],
    ],
];
