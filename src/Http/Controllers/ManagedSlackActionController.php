<?php

namespace Zereflab\LaravelBugReports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Zereflab\LaravelBugReports\Support\ReportState;

class ManagedSlackActionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $fingerprint = $request->string('fingerprint')->toString();
        $action = $request->string('action')->toString();
        $actionUrl = $request->string('action_url')->toString();
        $signature = $request->string('signature')->toString();

        if ($fingerprint === '' || $actionUrl === '' || $signature === '') {
            return response()->json(['message' => 'Invalid managed action payload.'], 422);
        }

        if (! in_array($action, [ReportState::ACTION_IGNORE, ReportState::ACTION_SOLVE], true)) {
            return response()->json(['message' => 'Unsupported action.'], 422);
        }

        if (! hash_equals($this->signature($fingerprint, $action, $actionUrl), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ($action === ReportState::ACTION_IGNORE) {
            ReportState::ignore($fingerprint);
            $status = 'ignored';
        } else {
            ReportState::solve($fingerprint);
            $status = 'solved';
        }

        return response()->json([
            'ok' => true,
            'fingerprint' => $fingerprint,
            'status' => $status,
        ]);
    }

    private function signature(string $fingerprint, string $action, string $actionUrl): string
    {
        return hash_hmac('sha256', implode('|', [$fingerprint, $action, $actionUrl]), $this->secret());
    }

    private function secret(): string
    {
        return (string) config('bug-reports.slack.actions.managed_callback_secret', config('app.key'));
    }
}
