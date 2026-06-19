<?php

namespace Zereflab\LaravelBugReports;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Zereflab\LaravelBugReports\Commands\PruneOccurrencesCommand;
use Zereflab\LaravelBugReports\Commands\TestBugReportCommand;
use Zereflab\LaravelBugReports\Http\Controllers\SlackActionController;
use Zereflab\LaravelBugReports\Logging\BugReportLogger;

class BugReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bug-reports.php', 'bug-reports');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/bug-reports.php' => config_path('bug-reports.php'),
        ], 'bug-reports-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'bug-reports-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'bug-reports');

        $this->registerAuthorization();
        $this->registerLogChannel();
        $this->registerRoutes();
        $this->registerDashboardRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                TestBugReportCommand::class,
                PruneOccurrencesCommand::class,
            ]);
        }
    }

    private function registerLogChannel(): void
    {
        $channel = config('bug-reports.channel', 'bug_reports');

        Config::set("logging.channels.{$channel}", [
            'driver' => 'custom',
            'via' => BugReportLogger::class,
            'level' => config('bug-reports.level', 'error'),
            'throttle_minutes' => config('bug-reports.throttle_minutes', 5),
            'reporter' => config('bug-reports.default', 'slack'),
            'throw' => false,
        ]);

        Log::extend('bug_reports', fn () => app(BugReportLogger::class)([
            'level' => config('bug-reports.level', 'error'),
            'throttle_minutes' => config('bug-reports.throttle_minutes', 5),
            'reporter' => config('bug-reports.default', 'slack'),
        ]));
    }

    private function registerRoutes(): void
    {
        if (! config('bug-reports.routes.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('bug-reports.routes.prefix', 'bug-reports'),
            'middleware' => $this->routeMiddleware(),
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/api.php'));

        foreach ($this->slackActionPrefixAliases() as $prefix) {
            Route::group([
                'prefix' => $prefix,
                'middleware' => $this->routeMiddleware(),
            ], fn () => $this->withoutCsrf(
                Route::post('slack/actions', SlackActionController::class)
            ));
        }
    }

    private function registerDashboardRoutes(): void
    {
        if (! config('bug-reports.dashboard.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('bug-reports.dashboard.path', 'bugs-report'),
            'middleware' => config('bug-reports.dashboard.middleware', ['web']),
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    private function registerAuthorization(): void
    {
        $ability = config('bug-reports.dashboard.gate', 'viewBugReports');

        if (Gate::has($ability)) {
            return;
        }

        // Deny by default. Applications must define the gate themselves or
        // allowlist user IDs via BUG_REPORTS_DASHBOARD_USER_IDS.
        Gate::define($ability, fn ($user = null): bool => false);
    }

    /**
     * @return array<int, string>
     */
    private function slackActionPrefixAliases(): array
    {
        $canonical = $this->normalizePrefix(config('bug-reports.routes.prefix', 'bug-reports'));

        return collect([
            config('bug-reports.dashboard.path', 'bugs-report'),
            'bugs-report',
            'bug-report',
            'bugs-reports',
        ])
            ->map(fn (mixed $prefix): string => $this->normalizePrefix($prefix))
            ->filter(fn (string $prefix): bool => $prefix !== '' && $prefix !== $canonical)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePrefix(mixed $prefix): string
    {
        return trim((string) $prefix, '/');
    }

    /**
     * Webhook route middleware with an appended rate limiter. Signature
     * verification protects state changes; the throttle caps abuse load.
     *
     * @return array<int, string>
     */
    private function routeMiddleware(): array
    {
        $middleware = (array) config('bug-reports.routes.middleware', ['api']);

        $throttle = config('bug-reports.routes.throttle', '60,1');

        if (filled($throttle)) {
            $middleware[] = 'throttle:'.$throttle;
        }

        return array_values(array_unique($middleware));
    }

    private function withoutCsrf(RoutingRoute $route): void
    {
        if (! class_exists(VerifyCsrfToken::class)) {
            return;
        }

        $route->withoutMiddleware([
            VerifyCsrfToken::class,
        ]);
    }
}
