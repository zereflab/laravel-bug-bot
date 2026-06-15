<?php

namespace Zereflab\LaravelBugReports\Support;

use Illuminate\Contracts\Bus\Dispatcher;

trait DispatchesQueuedWork
{
    /**
     * Dispatch a queueable job onto the configured connection/queue, or run it
     * inline when queueing is disabled (the default — keeps installs that have
     * no worker working out of the box).
     */
    protected function dispatchSlackWork(object $job): void
    {
        if (! config('bug-reports.queue.enabled', false)) {
            $job->handle();

            return;
        }

        if (($connection = config('bug-reports.queue.connection')) && method_exists($job, 'onConnection')) {
            $job->onConnection($connection);
        }

        if (($queue = config('bug-reports.queue.queue')) && method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }

        app(Dispatcher::class)->dispatch($job);
    }
}
