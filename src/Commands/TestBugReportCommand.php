<?php

namespace Zereflab\LaravelBugReports\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class TestBugReportCommand extends Command
{
    protected $signature = 'bug-reports:test {--message= : Custom exception message to send}';

    protected $description = 'Send a test exception through the Laravel Bug Reports channel';

    public function handle(): int
    {
        $channel = config('bug-reports.channel', 'bug_reports');

        if (blank(config('bug-reports.slack.bot_token'))) {
            $this->components->error('Missing BUG_REPORTS_SLACK_BOT_TOKEN.');
            $this->components->info(
                'No Slack app yet? Install the pre-built LaravelBugBot app and get your token in one click: '
                .config('bug-reports.slack.managed_install_url')
            );

            return self::FAILURE;
        }

        if (blank(config('bug-reports.slack.channel'))) {
            $this->components->error('Missing BUG_REPORTS_SLACK_CHANNEL.');

            return self::FAILURE;
        }

        $message = $this->option('message') ?: 'Laravel Bug Reports test '.now()->toIso8601String();

        try {
            throw new RuntimeException($message);
        } catch (RuntimeException $exception) {
            try {
                Config::set("logging.channels.{$channel}.throw", true);
                Log::forgetChannel($channel);

                Log::channel($channel)->error($exception->getMessage(), [
                    'exception' => $exception,
                    'source' => 'bug-reports:test',
                    'channel' => config('bug-reports.slack.channel'),
                ]);
            } catch (Throwable $slackException) {
                $this->components->error($slackException->getMessage());

                return self::FAILURE;
            } finally {
                Config::set("logging.channels.{$channel}.throw", false);
                Log::forgetChannel($channel);
            }
        }

        $this->components->info('Test exception sent to Slack channel '.config('bug-reports.slack.channel').'.');

        return self::SUCCESS;
    }
}
