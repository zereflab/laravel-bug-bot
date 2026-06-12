<?php

namespace Zereflab\LaravelBugReports\Logging;

use Monolog\Logger;

class BugReportLogger
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('bug-reports');

        $reporter = $config['reporter'] ?? config('bug-reports.default', 'slack');

        if ($reporter !== 'slack') {
            return $logger;
        }

        $logger->pushHandler(new SlackBugReportHandler(
            token: config('bug-reports.slack.bot_token'),
            channel: config('bug-reports.slack.channel'),
            username: config('bug-reports.slack.username', config('app.name', 'Laravel')),
            emoji: config('bug-reports.slack.emoji', ':boom:'),
            throttleMinutes: (int) ($config['throttle_minutes'] ?? config('bug-reports.throttle_minutes', 5)),
            throwOnFailure: (bool) ($config['throw'] ?? false),
            level: $config['level'] ?? config('bug-reports.level', 'error'),
            bubble: (bool) ($config['bubble'] ?? true),
        ));

        return $logger;
    }
}
