<?php

namespace Zereflab\LaravelBugReports\Logging;

use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use RuntimeException;
use Throwable;
use Zereflab\LaravelBugReports\Support\ReportState;

class SlackBugReportHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly ?string $token,
        private readonly ?string $channel,
        private readonly string $username,
        private readonly string $emoji,
        private readonly int $throttleMinutes,
        private readonly bool $throwOnFailure,
        string|int $level,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (blank($this->token) || blank($this->channel)) {
            return;
        }

        $exception = $record->context['exception'] ?? null;
        $exception = $exception instanceof Throwable ? $exception : null;
        $fingerprint = $this->fingerprint($record, $exception);
        $summary = $this->summary($record, $exception);
        ReportState::recordOccurrence($fingerprint, $this->reportAttributes($record, $exception));

        if (ReportState::isIgnored($fingerprint)) {
            return;
        }

        if ($this->throttleMinutes > 0 && ! Cache::add(
            ReportState::throttleKey($fingerprint),
            true,
            now()->addMinutes($this->throttleMinutes)
        )) {
            return;
        }

        try {
            $response = Http::withToken($this->token)
                ->asJson()
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $this->channel,
                    'username' => $this->username,
                    'icon_emoji' => $this->emoji,
                    'text' => $summary,
                    'blocks' => $this->parentBlocks($summary, $fingerprint),
                ]);

            if (! $response->successful() || $response->json('ok') !== true) {
                $this->fail('Slack parent message failed with error ['.($response->json('error') ?: $response->status()).'].');

                return;
            }

            $threadTimestamp = $response->json('ts');

            if (! is_string($threadTimestamp) || $threadTimestamp === '') {
                return;
            }

            ReportState::storeMessage($fingerprint, [
                'channel' => $this->channel,
                'ts' => $threadTimestamp,
                'summary' => $summary,
            ]);

            foreach ($this->threadMessages($record, $exception) as $message) {
                $replyResponse = Http::withToken($this->token)
                    ->asJson()
                    ->post('https://slack.com/api/chat.postMessage', [
                        'channel' => $this->channel,
                        'username' => $this->username,
                        'icon_emoji' => $this->emoji,
                        'thread_ts' => $threadTimestamp,
                        'text' => $message,
                    ]);

                if (! $replyResponse->successful() || $replyResponse->json('ok') !== true) {
                    $this->fail('Slack thread reply failed with error ['.($replyResponse->json('error') ?: $replyResponse->status()).'].');
                }
            }
        } catch (Throwable $exception) {
            if ($this->throwOnFailure) {
                throw $exception;
            }
        }
    }

    private function fail(string $message): void
    {
        if ($this->throwOnFailure) {
            throw new RuntimeException($message);
        }
    }

    private function summary(LogRecord $record, ?Throwable $exception): string
    {
        $title = $exception
            ? $record->level->getName().' '.class_basename($exception)
            : $record->level->getName().' Log';

        $lines = [
            ':rotating_light: *'.$title.'*',
            '*When:* '.$this->formattedDate($record),
            '*Message:* '.$this->inlineValue($exception?->getMessage() ?: $record->message),
        ];

        if ($origin = $this->origin($record, $exception)) {
            $lines[] = '*Origin:* '.$this->inlineValue($origin);
        }

        if ($entity = $this->entity($record)) {
            $lines[] = '*Entity:* '.$this->inlineValue($entity);
        }

        if ($exception) {
            $lines[] = '*Location:* '.$this->inlineValue($exception->getFile().':'.$exception->getLine());
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parentBlocks(string $summary, string $fingerprint): array
    {
        if (! $this->slackActionsEnabled()) {
            return [[
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $summary],
            ]];
        }

        return [
            [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $summary],
            ],
            [
                'type' => 'context',
                'elements' => [[
                    'type' => 'mrkdwn',
                    'text' => '*Fingerprint:* `'.$fingerprint.'`',
                ]],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Solved'],
                        'style' => 'primary',
                        'action_id' => ReportState::ACTION_SOLVE,
                        'value' => $fingerprint,
                    ],
                    [
                        'type' => 'button',
                        'text' => ['type' => 'plain_text', 'text' => 'Ignore'],
                        'style' => 'danger',
                        'action_id' => ReportState::ACTION_IGNORE,
                        'value' => $fingerprint,
                    ],
                ],
            ],
        ];
    }

    private function slackActionsEnabled(): bool
    {
        return config('bug-reports.slack.app_mode', 'own') !== 'managed'
            && (bool) config('bug-reports.slack.actions.enabled', true);
    }

    private function formattedDate(LogRecord $record): string
    {
        return $record->datetime
            ->setTimezone(new DateTimeZone(config('app.timezone', 'UTC')))
            ->format('l, F j, Y \a\t g:i:s A T');
    }

    private function origin(LogRecord $record, ?Throwable $exception): ?string
    {
        foreach (['source', 'command', 'job', 'controller', 'action'] as $key) {
            if (filled($record->context[$key] ?? null)) {
                return $this->stringifyContextValue($record->context[$key]);
            }
        }

        if (! app()->runningInConsole() && request()->route()) {
            $action = request()->route()->getActionName();

            if ($action !== 'Closure') {
                return 'Controller '.$action;
            }
        }

        if (app()->runningInConsole() && filled($_SERVER['argv'][1] ?? null)) {
            return 'Command '.$_SERVER['argv'][1];
        }

        if (! $exception) {
            return null;
        }

        foreach ($exception->getTrace() as $frame) {
            $class = $frame['class'] ?? null;

            if (! is_string($class) || ! str_starts_with($class, 'App\\')) {
                continue;
            }

            $function = $frame['function'] ?? '__invoke';
            $type = match (true) {
                str_ends_with($class, 'Controller') => 'Controller',
                str_contains($class, '\\Jobs\\') => 'Job',
                str_contains($class, '\\Console\\Commands\\') => 'Command',
                default => 'Application',
            };

            return $type.' '.$class.'::'.$function;
        }

        return null;
    }

    private function entity(LogRecord $record): ?string
    {
        if (! app()->runningInConsole() && request()->user()) {
            return $this->modelLabel(request()->user());
        }

        foreach (['user', 'model', 'entity'] as $key) {
            $value = $record->context[$key] ?? null;

            if ($value instanceof Model) {
                return $this->modelLabel($value);
            }

            if (is_array($value)) {
                return $this->arrayEntityLabel($key, $value);
            }
        }

        foreach ($record->context as $key => $value) {
            if ($value instanceof Model) {
                return $this->modelLabel($value);
            }

            if (str_ends_with((string) $key, '_id') && filled($value)) {
                $email = $record->context[str_replace('_id', '_email', (string) $key)] ?? $record->context['email'] ?? null;

                return str_replace('_', ' ', (string) $key).': '.$value.($email ? ' <'.$email.'>' : '');
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function threadMessages(LogRecord $record, ?Throwable $exception): array
    {
        $details = $exception
            ? $this->formatException($record, $exception)
            : $this->formatRecord($record);

        if (! is_string($details) || $details === '') {
            $details = $record->message;
        }

        $chunks = str_split($details, 30000);
        $total = count($chunks);

        return collect($chunks)
            ->map(fn (string $chunk, int $index): string => sprintf(
                "*Full exception%s*\n%s",
                $total > 1 ? ' '.($index + 1)."/{$total}" : '',
                $chunk
            ))
            ->all();
    }

    private function formatException(LogRecord $record, Throwable $exception): string
    {
        $context = collect($record->context)
            ->except('exception')
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        $request = request();

        $sections = [
            "*Exception*\n".$this->codeBlock($exception::class),
            "*Message*\n".$this->codeBlock($exception->getMessage() ?: '(empty message)'),
            "*Location*\n".$this->codeBlock($exception->getFile().':'.$exception->getLine()),
            "*Level*\n".$this->codeBlock($record->level->getName()),
            "*Environment*\n".$this->codeBlock(app()->environment()),
        ];

        if (! app()->runningInConsole()) {
            $sections[] = "*Request*\n".$this->codeBlock(collect([
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ])->filter(fn (mixed $value): bool => filled($value))->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($context !== []) {
            $sections[] = "*Context*\n".$this->codeBlock(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
        }

        $sections[] = "*Stack trace*\n".$this->codeBlock($exception->getTraceAsString());

        if ($exception->getPrevious()) {
            $sections[] = "*Previous exception*\n".$this->codeBlock((string) $exception->getPrevious());
        }

        return implode("\n\n", $sections);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportAttributes(LogRecord $record, ?Throwable $exception): array
    {
        return [
            'level' => $record->level->getName(),
            'exception_class' => $exception ? $exception::class : null,
            'message' => $exception?->getMessage() ?: $record->message,
            'origin' => $this->origin($record, $exception),
            'entity' => $this->entity($record),
            'file' => $exception?->getFile(),
            'line' => $exception?->getLine(),
            'context' => $this->serializableContext($record),
            'request' => $this->requestContext(),
            'stack_trace' => $exception?->getTraceAsString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializableContext(LogRecord $record): ?array
    {
        $context = collect($record->context)
            ->except('exception')
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();

        if ($context === []) {
            return null;
        }

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return is_string($encoded) ? json_decode($encoded, true) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestContext(): ?array
    {
        if (app()->runningInConsole()) {
            return null;
        }

        $request = request();

        return collect([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ])->filter(fn (mixed $value): bool => filled($value))->all();
    }

    private function formatRecord(LogRecord $record): string
    {
        return implode("\n\n", [
            "*Message*\n".$this->codeBlock($record->message),
            "*Level*\n".$this->codeBlock($record->level->getName()),
            "*Details*\n".$this->codeBlock(is_string($record->formatted) ? $record->formatted : json_encode($record->formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)),
        ]);
    }

    private function stringifyContextValue(mixed $value): string
    {
        if ($value instanceof Model) {
            return $this->modelLabel($value);
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: 'array';
        }

        return (string) $value;
    }

    private function modelLabel(Model $model): string
    {
        $parts = [class_basename($model).' #'.($model->getKey() ?? 'unsaved')];

        foreach (['email', 'username', 'name'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (filled($value)) {
                $parts[] = $attribute.': '.$value;
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function arrayEntityLabel(string $key, array $value): string
    {
        $parts = [str($key)->headline()->toString()];

        foreach (['id', 'user_id', 'email', 'username', 'name'] as $attribute) {
            if (filled($value[$attribute] ?? null)) {
                $parts[] = $attribute.': '.$value[$attribute];
            }
        }

        return implode(' | ', $parts);
    }

    private function inlineValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: $value);

        if (strlen($value) > 1200) {
            $value = substr($value, 0, 1197).'...';
        }

        return '`'.str_replace('`', "'", $value).'`';
    }

    private function codeBlock(?string $value): string
    {
        $value = $value !== null && $value !== '' ? $value : '(none)';

        return "```\n".str_replace('```', "`\u{200B}``", $value)."\n```";
    }

    private function fingerprint(LogRecord $record, ?Throwable $exception): string
    {
        if ($exception) {
            return sha1(implode('|', [
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ]));
        }

        return sha1($record->level->getName().'|'.$record->message);
    }
}
