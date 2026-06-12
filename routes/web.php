<?php

use Illuminate\Support\Facades\Route;
use Zereflab\LaravelBugReports\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('bug-reports.dashboard');
Route::get('/{status}', [DashboardController::class, 'index'])
    ->whereIn('status', ['all', 'pending', 'solved', 'ignored'])
    ->name('bug-reports.dashboard.status');

Route::post('/reports/{bugReport}/solve', [DashboardController::class, 'solve'])->name('bug-reports.dashboard.solve');
Route::post('/reports/{bugReport}/ignore', [DashboardController::class, 'ignore'])->name('bug-reports.dashboard.ignore');
Route::delete('/reports/{bugReport}', [DashboardController::class, 'delete'])->name('bug-reports.dashboard.delete');
