<?php

use Illuminate\Support\Facades\Route;
use Zereflab\LaravelBugReports\Http\Controllers\ManagedSlackActionController;
use Zereflab\LaravelBugReports\Http\Controllers\SlackActionController;

$slackActionRoutes = [
    Route::post('slack/actions', SlackActionController::class)
        ->name('bug-reports.slack.actions'),
    Route::post('managed/actions', ManagedSlackActionController::class)
        ->name('bug-reports.managed.actions'),
];

if (class_exists(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)) {
    foreach ($slackActionRoutes as $slackActionRoute) {
        $slackActionRoute->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);
    }
}
