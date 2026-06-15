<?php

namespace Zereflab\LaravelBugReports\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Zereflab\LaravelBugReports\Jobs\DeliverBugReport;
use Zereflab\LaravelBugReports\Models\BugReport;
use Zereflab\LaravelBugReports\Models\BugReportOccurrence;
use Zereflab\LaravelBugReports\Support\ReportState;
use Zereflab\LaravelBugReports\Tests\TestCase;

class BugReportsTest extends TestCase
{
    public function test_it_sends_a_parent_message_and_threaded_exception(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        Log::channel('bug_reports')->error('Payment callback failed.', [
            'exception' => new RuntimeException('Payment callback failed.'),
            'user_id' => 123,
            'source' => 'test',
        ]);

        Http::assertSentCount(2);

        $requests = Http::recorded();
        $parent = $requests[0][0]->data();
        $thread = $requests[1][0]->data();

        $this->assertSame('C1234567890', $parent['channel']);
        $this->assertStringContainsString(':rotating_light: *ERROR RuntimeException*', $parent['text']);
        $this->assertStringContainsString('*Message:* `Payment callback failed.`', $parent['text']);
        $this->assertStringContainsString('*Entity:* `user id: 123`', $parent['text']);
        $this->assertSame('actions', $parent['blocks'][2]['type']);

        $this->assertSame('171819.0001', $thread['thread_ts']);
        $this->assertStringContainsString('*Full exception*', $thread['text']);
        $this->assertStringContainsString('*Stack trace*', $thread['text']);
    }

    public function test_managed_slack_app_mode_renders_signed_action_buttons(): void
    {
        config()->set('bug-reports.slack.app_mode', 'managed');
        config()->set('bug-reports.slack.actions.enabled', true);
        config()->set('bug-reports.slack.actions.managed_callback_url', 'https://client.test/bug-reports/managed/actions');
        Log::forgetChannel('bug_reports');
        Cache::flush();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        Log::channel('bug_reports')->error('Managed app failure.', [
            'exception' => new RuntimeException('Managed app failure.'),
        ]);

        $parent = Http::recorded()[0][0]->data();
        $value = json_decode($parent['blocks'][2]['elements'][0]['value'], true);

        $this->assertSame('actions', $parent['blocks'][2]['type']);
        $this->assertSame(2, $value['v']);
        $this->assertSame('https://client.test/bug-reports/managed/actions', $value['action_url']);
        $this->assertSame(ReportState::ACTION_SOLVE, $value['action']);
        $this->assertIsString($value['fingerprint']);
        // Signature must bind the action so a solve signature cannot be replayed as ignore.
        $secret = (string) config('bug-reports.slack.actions.managed_callback_secret', config('app.key'));
        $this->assertSame(
            hash_hmac('sha256', implode('|', [$value['fingerprint'], $value['action'], $value['action_url']]), $secret),
            $value['signature']
        );
    }

    public function test_it_throttles_duplicate_exceptions(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        $exception = new RuntimeException('Payment callback failed.');

        Log::channel('bug_reports')->error($exception->getMessage(), ['exception' => $exception]);
        Log::channel('bug_reports')->error($exception->getMessage(), ['exception' => $exception]);

        Http::assertSentCount(2);
    }

    public function test_ignored_fingerprints_are_not_sent(): void
    {
        Cache::flush();
        Http::fake();

        $exception = new RuntimeException('Payment callback failed.');
        Cache::forever(ReportState::statusKey($this->fingerprint($exception)), 'ignored');

        Log::channel('bug_reports')->error($exception->getMessage(), ['exception' => $exception]);

        Http::assertNothingSent();
    }

    public function test_slack_solve_action_updates_all_matching_messages(): void
    {
        Cache::flush();
        Cache::forever(config('bug-reports.cache_prefix').':messages:test-fingerprint', [
            ['channel' => 'C1234567890', 'ts' => '111.111', 'summary' => 'First matching error'],
            ['channel' => 'C1234567890', 'ts' => '222.222', 'summary' => 'Second matching error'],
        ]);

        Http::fakeSequence()
            ->push(['ok' => true])
            ->push(['ok' => true]);

        $this->postSlackAction([
            'actions' => [[
                'action_id' => ReportState::ACTION_SOLVE,
                'value' => 'test-fingerprint',
            ]],
            'user' => ['username' => 'akash'],
        ])->assertOk();

        Http::assertSentCount(2);

        $requests = Http::recorded();

        $this->assertSame('111.111', $requests[0][0]->data()['ts']);
        $this->assertStringContainsString('*Solved* by akash', $requests[0][0]->data()['blocks'][1]['elements'][0]['text']);
        $this->assertSame('222.222', $requests[1][0]->data()['ts']);
    }

    public function test_slack_ignore_action_suppresses_future_matching_messages(): void
    {
        Cache::flush();
        Http::fake();

        $this->postSlackAction([
            'actions' => [[
                'action_id' => ReportState::ACTION_IGNORE,
                'value' => 'test-fingerprint',
            ]],
            'user' => ['username' => 'akash'],
        ])->assertOk();

        $this->assertSame('ignored', Cache::get(ReportState::statusKey('test-fingerprint')));
    }

    public function test_the_test_command_reports_slack_failures(): void
    {
        Cache::flush();
        Log::forgetChannel('bug_reports');
        Http::fakeSequence()
            ->push(['ok' => false, 'error' => 'channel_not_found']);

        $this->artisan('bug-reports:test --message="Manual test"')
            ->expectsOutputToContain('Slack parent message failed with error [channel_not_found].')
            ->assertFailed();
    }

    public function test_it_persists_bug_reports_and_occurrences(): void
    {
        $this->artisan('migrate')->run();
        Cache::flush();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        Log::channel('bug_reports')->error('Persisted failure.', [
            'exception' => new RuntimeException('Persisted failure.'),
            'source' => 'persistence-test',
        ]);

        $this->assertSame(1, BugReport::query()->count());
        $this->assertSame(1, BugReportOccurrence::query()->count());

        $report = BugReport::query()->first();

        $this->assertSame('pending', $report->status);
        $this->assertSame('persistence-test', $report->origin);
        $this->assertSame(1, $report->occurrences);
    }

    public function test_record_occurrence_counts_atomically_and_keeps_one_row(): void
    {
        $this->artisan('migrate')->run();

        $attributes = [
            'level' => 'ERROR',
            'exception_class' => 'RuntimeException',
            'message' => 'race',
            'origin' => 'race-test',
        ];

        ReportState::recordOccurrence('race-fp', $attributes);
        ReportState::recordOccurrence('race-fp', $attributes);
        ReportState::recordOccurrence('race-fp', $attributes);

        $report = BugReport::query()->where('fingerprint', 'race-fp')->first();

        $this->assertSame(1, BugReport::query()->where('fingerprint', 'race-fp')->count());
        $this->assertSame(3, (int) $report->occurrences);
        $this->assertSame(3, BugReportOccurrence::query()->where('fingerprint', 'race-fp')->count());
    }

    public function test_record_occurrence_reopens_a_solved_report(): void
    {
        $this->artisan('migrate')->run();

        $attributes = ['level' => 'ERROR', 'exception_class' => 'RuntimeException', 'message' => 'reopen'];

        ReportState::recordOccurrence('reopen-fp', $attributes);
        ReportState::solve('reopen-fp');
        $this->assertSame('solved', BugReport::query()->where('fingerprint', 'reopen-fp')->first()->status);

        $report = ReportState::recordOccurrence('reopen-fp', $attributes);

        $this->assertSame('pending', $report->status);
        $this->assertNull($report->solved_at);
        $this->assertSame(2, (int) BugReport::query()->where('fingerprint', 'reopen-fp')->first()->occurrences);
    }

    public function test_dashboard_is_denied_by_default(): void
    {
        $this->artisan('migrate')->run();

        $this->get('/bugs-report')->assertForbidden();

        $this->actingAs($this->userWithId(99))
            ->get('/bugs-report')
            ->assertForbidden();
    }

    public function test_dashboard_can_be_viewed_by_configured_user_id(): void
    {
        $this->artisan('migrate')->run();
        config()->set('bug-reports.dashboard.user_ids', ['1']);

        BugReport::query()->create([
            'fingerprint' => 'dashboard-fingerprint',
            'status' => 'pending',
            'level' => 'ERROR',
            'exception_class' => RuntimeException::class,
            'message' => 'Dashboard failure.',
            'origin' => 'dashboard-test',
            'occurrences' => 3,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->userWithId(1))
            ->get('/bugs-report')
            ->assertOk()
            ->assertSee('Dashboard failure.')
            ->assertSee('dashboard-test');
    }

    public function test_dashboard_actions_update_report_state(): void
    {
        $this->artisan('migrate')->run();
        config()->set('bug-reports.dashboard.user_ids', ['1']);

        $report = BugReport::query()->create([
            'fingerprint' => 'action-fingerprint',
            'status' => 'pending',
            'message' => 'Action failure.',
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->userWithId(1))
            ->post("/bugs-report/reports/{$report->id}/ignore")
            ->assertRedirect();

        $this->assertSame('ignored', $report->fresh()->status);

        $this->actingAs($this->userWithId(1))
            ->post("/bugs-report/reports/{$report->id}/solve")
            ->assertRedirect();

        $this->assertSame('solved', $report->fresh()->status);

        $this->actingAs($this->userWithId(1))
            ->delete("/bugs-report/reports/{$report->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing(config('bug-reports.database.table'), [
            'id' => $report->id,
        ]);
    }

    public function test_slack_action_compatibility_aliases_are_registered(): void
    {
        foreach ([
            '/bug-reports/slack/actions',
            '/bugs-report/slack/actions',
            '/bug-report/slack/actions',
            '/bugs-reports/slack/actions',
        ] as $uri) {
            $this->post($uri)->assertUnauthorized();
        }
    }

    public function test_managed_action_callback_updates_report_state(): void
    {
        $this->artisan('migrate')->run();
        config()->set('bug-reports.slack.actions.managed_callback_secret', 'managed-secret');

        $report = BugReport::query()->create([
            'fingerprint' => 'managed-fingerprint',
            'status' => 'pending',
            'message' => 'Managed action failure.',
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $actionUrl = 'https://client.test/bug-reports/managed/actions';

        $this->postJson('/bug-reports/managed/actions', [
            'fingerprint' => 'managed-fingerprint',
            'action' => ReportState::ACTION_SOLVE,
            'action_url' => $actionUrl,
            'signature' => hash_hmac('sha256', implode('|', ['managed-fingerprint', ReportState::ACTION_SOLVE, $actionUrl]), 'managed-secret'),
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'solved');

        $this->assertSame('solved', $report->fresh()->status);
    }

    public function test_managed_action_signature_is_bound_to_the_action(): void
    {
        $this->artisan('migrate')->run();
        config()->set('bug-reports.slack.actions.managed_callback_secret', 'managed-secret');

        $report = BugReport::query()->create([
            'fingerprint' => 'managed-fingerprint',
            'status' => 'pending',
            'message' => 'Managed action failure.',
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $actionUrl = 'https://client.test/bug-reports/managed/actions';

        // Signature was issued for SOLVE; replaying it as IGNORE must be rejected.
        $solveSignature = hash_hmac('sha256', implode('|', ['managed-fingerprint', ReportState::ACTION_SOLVE, $actionUrl]), 'managed-secret');

        $this->postJson('/bug-reports/managed/actions', [
            'fingerprint' => 'managed-fingerprint',
            'action' => ReportState::ACTION_IGNORE,
            'action_url' => $actionUrl,
            'signature' => $solveSignature,
        ])->assertUnauthorized();

        $this->assertSame('pending', $report->fresh()->status);
    }

    public function test_it_redacts_sensitive_request_and_context_data(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        $this->get('/?token=super-secret-value&page=2');

        Log::channel('bug_reports')->error('Redaction failure.', [
            'exception' => new RuntimeException('Redaction failure.'),
            'password' => 'hunter2',
            'safe' => 'visible',
        ]);

        $thread = Http::recorded()[1][0]->data();

        $this->assertStringNotContainsString('super-secret-value', $thread['text']);
        $this->assertStringNotContainsString('hunter2', $thread['text']);
        $this->assertStringContainsString('[redacted]', $thread['text']);
        $this->assertStringContainsString('visible', $thread['text']);
    }

    public function test_it_queues_slack_delivery_when_queueing_is_enabled(): void
    {
        config()->set('bug-reports.queue.enabled', true);
        Cache::flush();
        Bus::fake();
        Http::fake();

        Log::channel('bug_reports')->error('Queued failure.', [
            'exception' => new RuntimeException('Queued failure.'),
        ]);

        Bus::assertDispatched(DeliverBugReport::class);
        Http::assertNothingSent();
    }

    public function test_it_delivers_inline_when_queueing_is_disabled(): void
    {
        config()->set('bug-reports.queue.enabled', false);
        Cache::flush();
        Bus::fake();
        Http::fakeSequence()
            ->push(['ok' => true, 'ts' => '171819.0001'])
            ->push(['ok' => true]);

        Log::channel('bug_reports')->error('Inline failure.', [
            'exception' => new RuntimeException('Inline failure.'),
        ]);

        Bus::assertNotDispatched(DeliverBugReport::class);
        Http::assertSentCount(2);
    }

    private function postSlackAction(array $payload)
    {
        $body = http_build_query(['payload' => json_encode($payload)]);
        $timestamp = (string) time();
        $signature = 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$body, 'test-signing-secret');

        return $this->call('POST', '/bug-reports/slack/actions', ['payload' => json_encode($payload)], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => $signature,
        ], $body);
    }

    private function fingerprint(Throwable $exception): string
    {
        return sha1(implode('|', [
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ]));
    }

    private function userWithId(int $id): Authenticatable
    {
        $user = new Authenticatable;
        $user->setAttribute('id', $id);

        return $user;
    }
}
