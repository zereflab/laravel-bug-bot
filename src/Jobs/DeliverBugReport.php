<?php

namespace Zereflab\LaravelBugReports\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Zereflab\LaravelBugReports\Support\ReportState;

class DeliverBugReport implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $parentBlocks
     * @param  array<int, string>  $threadMessages
     */
    public function __construct(
        private readonly string $token,
        private readonly string $channel,
        private readonly string $username,
        private readonly string $emoji,
        private readonly string $summary,
        private readonly array $parentBlocks,
        private readonly array $threadMessages,
        private readonly string $fingerprint,
        private readonly bool $throwOnFailure = false,
    ) {}

    public function handle(): void
    {
        try {
            $response = Http::withToken($this->token)
                ->connectTimeout(3)
                ->timeout(5)
                ->asJson()
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $this->channel,
                    'username' => $this->username,
                    'icon_emoji' => $this->emoji,
                    'text' => $this->summary,
                    'blocks' => $this->parentBlocks,
                ]);

            if (! $response->successful() || $response->json('ok') !== true) {
                $this->failDelivery('Slack parent message failed with error ['.($response->json('error') ?: $response->status()).'].');

                return;
            }

            $threadTimestamp = $response->json('ts');

            if (! is_string($threadTimestamp) || $threadTimestamp === '') {
                return;
            }

            $threadReplyTimestamps = [];

            foreach ($this->threadMessages as $message) {
                $replyResponse = Http::withToken($this->token)
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->asJson()
                    ->post('https://slack.com/api/chat.postMessage', [
                        'channel' => $this->channel,
                        'username' => $this->username,
                        'icon_emoji' => $this->emoji,
                        'thread_ts' => $threadTimestamp,
                        'text' => $message,
                    ]);

                if (! $replyResponse->successful() || $replyResponse->json('ok') !== true) {
                    $this->failDelivery('Slack thread reply failed with error ['.($replyResponse->json('error') ?: $replyResponse->status()).'].');

                    continue;
                }

                $replyTimestamp = $replyResponse->json('ts');

                if (is_string($replyTimestamp) && $replyTimestamp !== '') {
                    $threadReplyTimestamps[] = $replyTimestamp;
                }
            }

            ReportState::storeMessage($this->fingerprint, [
                'channel' => $this->channel,
                'ts' => $threadTimestamp,
                'summary' => $this->summary,
                'thread_ts' => $threadReplyTimestamps,
            ]);
        } catch (Throwable $exception) {
            if ($this->throwOnFailure) {
                throw $exception;
            }
        }
    }

    private function failDelivery(string $message): void
    {
        if ($this->throwOnFailure) {
            throw new RuntimeException($message);
        }
    }
}
