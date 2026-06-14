<?php

use Illuminate\Support\Facades\Route;
use Zereflab\LaravelBugReports\Http\Controllers\SlackActionController;

$slackActionRoute = Route::post('slack/actions', SlackActionController::class)
    ->name('bug-reports.slack.actions');

if (class_exists(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)) {
    $slackActionRoute->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);
}
