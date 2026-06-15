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
        $fingerprint = $action['value'] ?? null;
        $user = $payload['user']['username'] ?? $payload['user']['name'] ?? $payload['user']['id'] ?? 'Slack user';

        if (! is_string($fingerprint) || ! in_array($actionId, [ReportState::ACTION_IGNORE, ReportState::ACTION_SOLVE], true)) {
            return response('Unsupported action.', 422);
        }

        $status = $actionId === ReportState::ACTION_IGNORE ? 'ignored' : 'solved';

        if ($status === 'ignored') {
            ReportState::ignore($fingerprint);
        } else {
            ReportState::solve($fingerprint);
        }

        $this->updateMessages($fingerprint, $status, $user);

        return response($status === 'ignored'
            ? 'Ignored this error fingerprint.'
            : 'Marked this error fingerprint as solved.'
        );
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

    private function updateMessages(string $fingerprint, string $status, string $user): void
    {
        $token = config('bug-reports.slack.bot_token');

        if (blank($token)) {
            return;
        }

        $this->dispatchSlackWork(new UpdateSlackMessages((string) $token, $fingerprint, $status, $user));
    }
}
