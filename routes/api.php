<?php

use Illuminate\Support\Facades\Route;
use Zereflab\LaravelBugReports\Http\Controllers\SlackActionController;

Route::post('slack/actions', SlackActionController::class)->name('bug-reports.slack.actions');
