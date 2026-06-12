<?php

namespace Zereflab\LaravelBugReports\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
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
