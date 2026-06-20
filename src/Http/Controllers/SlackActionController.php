<?php

namespace Zereflab\LaravelBugReports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Zereflab\LaravelBugReports\Jobs\UpdateSlackMessages;
use Zereflab\LaravelBugReports\Support\DispatchesQueuedWork;
use Zereflab\LaravelBugReports\Support\ReportState;

class SlackActionController extends Controller
{
    use DispatchesQueuedWork;

    public function __invoke(Request $request): Response
    {
        if (! $this->hasValidSignature($request)) {
            return response('Invalid signature.', 401);
        }

        $payload = json_decode((string) $request->input('payload'), true);

        if (! is_array($payload)) {
            return response('Invalid payload.', 422);
        }

        $action = $payload['actions'][0] ?? null;
        $actionId = $action['action_id'] ?? null;
        $fingerprint = $this->fingerprintFromActionValue($action['value'] ?? null);
        $user = $payload['user']['username'] ?? $payload['user']['name'] ?? $payload['user']['id'] ?? 'Slack user';

        if (! is_string($fingerprint) || ! in_array($actionId, [ReportState::ACTION_IGNORE, ReportState::ACTION_SOLVE, ReportState::ACTION_DELETE], true)) {
            return response('Unsupported action.', 422);
        }

        $status = $this->statusFromAction($actionId);

        if ($status === 'deleted') {
            $this->updateMessages($fingerprint, $status, $user, ReportState::messages($fingerprint));
            ReportState::delete($fingerprint);
        } else {
            $this->applyAction($fingerprint, $actionId);
            $this->updateMessages($fingerprint, $status, $user);
        }

        return response(match ($status) {
            'ignored' => 'Ignored this error fingerprint.',
            'deleted' => 'Deleted this error fingerprint.',
            default => 'Marked this error fingerprint as solved.',
        });
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = config('bug-reports.slack.signing_secret');

        if (blank($secret)) {
            return false;
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! is_string($timestamp) || ! is_string($signature) || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $base = 'v0:'.$timestamp.':'.$request->getContent();
        $expected = 'v0='.hash_hmac('sha256', $base, $secret);

        return hash_equals($expected, $signature);
    }

    private function fingerprintFromActionValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded) && is_string($decoded['fingerprint'] ?? null) && $decoded['fingerprint'] !== '') {
            return $decoded['fingerprint'];
        }

        return $value;
    }

    private function updateMessages(string $fingerprint, string $status, string $user, ?array $messages = null): void
    {
        $token = config('bug-reports.slack.bot_token');

        if (blank($token)) {
            return;
        }

        $this->dispatchSlackWork(new UpdateSlackMessages((string) $token, $fingerprint, $status, $user, $messages));
    }

    private function applyAction(string $fingerprint, string $actionId): void
    {
        if ($actionId === ReportState::ACTION_IGNORE) {
            ReportState::ignore($fingerprint);

            return;
        }

        ReportState::solve($fingerprint);
    }

    private function statusFromAction(string $actionId): string
    {
        return match ($actionId) {
            ReportState::ACTION_IGNORE => 'ignored',
            ReportState::ACTION_DELETE => 'deleted',
            default => 'solved',
        };
    }
}
