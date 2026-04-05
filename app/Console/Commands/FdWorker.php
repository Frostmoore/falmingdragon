<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Convenience wrapper around `queue:work` with FlamingDragon-appropriate defaults.
 */
class FdWorker extends Command
{
    protected $signature = 'fd:worker
        {--queue=default : Queue name to process}
        {--tries=1 : Number of retry attempts}
        {--timeout=600 : Maximum seconds a job may run}
        {--sleep=3 : Seconds to sleep when no job is available}';

    protected $description = 'Start the FlamingDragon queue worker (wrapper around queue:work).';

    public function handle(): int
    {
        $queue   = $this->option('queue');
        $tries   = $this->option('tries');
        $timeout = $this->option('timeout');
        $sleep   = $this->option('sleep');

        $this->info("[FdWorker] Starting queue worker on queue '{$queue}'...");

        return $this->call('queue:work', [
            '--queue'   => $queue,
            '--tries'   => $tries,
            '--timeout' => $timeout,
            '--sleep'   => $sleep,
        ]);
    }
}
