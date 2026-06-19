<?php

namespace Zereflab\LaravelBugReports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Zereflab\LaravelBugReports\Support\ReportState;

class UpdateSlackMessages implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $fingerprint,
        private readonly string $status,
        private readonly string $user,
    ) {}

    public function handle(): void
    {
        foreach (ReportState::messages($this->fingerprint) as $message) {
            Http::withToken($this->token)
                ->connectTimeout(3)
                ->timeout(5)
                ->asJson()
                ->post('https://slack.com/api/chat.update', [
                    'channel' => $message['channel'],
                    'ts' => $message['ts'],
                    'text' => $message['summary']."\nStatus: {$this->status} by {$this->user}",
                    'blocks' => $this->updatedBlocks($message['summary']),
                ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function updatedBlocks(string $summary): array
    {
        $label = $this->status === 'ignored' ? ':no_entry: *Ignored*' : ':white_check_mark: *Solved*';

        return [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $summary],
            ],
            [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => "{$label} by {$this->user} • Fingerprint: `{$this->fingerprint}`",
                ]],
            ],
        ];
    }
}
