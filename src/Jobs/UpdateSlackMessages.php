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
        private readonly ?array $messages = null,
    ) {}

    public function handle(): void
    {
        foreach ($this->messages ?? ReportState::messages($this->fingerprint) as $message) {
            if ($this->status === 'deleted') {
                $this->deleteMessageThread($message);

                continue;
            }

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
        $label = match ($this->status) {
            'ignored' => ':large_yellow_circle: *Ignored*',
            'deleted' => ':red_circle: *Deleted*',
            default => ':large_green_circle: *Solved*',
        };

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

    /**
     * @param  array{channel: string, ts: string, summary: string, thread_ts?: array<int, string>}  $message
     */
    private function deleteMessageThread(array $message): void
    {
        foreach (array_reverse($message['thread_ts'] ?? []) as $threadTimestamp) {
            $this->deleteSlackMessage($message['channel'], $threadTimestamp);
        }

        $this->deleteSlackMessage($message['channel'], $message['ts']);
    }

    private function deleteSlackMessage(string $channel, string $timestamp): void
    {
        Http::withToken($this->token)
            ->connectTimeout(3)
            ->timeout(5)
            ->asJson()
            ->post('https://slack.com/api/chat.delete', [
                'channel' => $channel,
                'ts' => $timestamp,
            ]);
    }
}
