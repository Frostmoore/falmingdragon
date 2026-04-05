<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AgentStatus;
use App\Models\AgentSession;
use App\Services\Memory\MemoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FdHeartbeat extends Command
{
    protected $signature   = 'fd:heartbeat';
    protected $description = 'FlamingDragon periodic heartbeat: clean up expired sessions, sweep memory, check queue health.';

    public function __construct(
        private readonly MemoryService $memoryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('flamingdragon.heartbeat.enabled', true)) {
            return self::SUCCESS;
        }

        $this->info('[FdHeartbeat] Running...');

        foreach (config('flamingdragon.heartbeat.tasks', []) as $task) {
            try {
                $this->runTask($task);
            } catch (Throwable $e) {
                $this->error("[FdHeartbeat] Task '{$task}' failed: {$e->getMessage()}");
                Log::error('[FdHeartbeat] Task failed.', ['task' => $task, 'error' => $e->getMessage()]);
            }
        }

        $this->info('[FdHeartbeat] Done.');
        return self::SUCCESS;
    }

    private function runTask(string $task): void
    {
        match($task) {
            'cleanup_expired_sessions' => $this->cleanupExpiredSessions(),
            'check_queue_health'       => $this->checkQueueHealth(),
            'memory_expiration_sweep'  => $this->memorySweep(),
            default                    => $this->warn("Unknown heartbeat task: {$task}"),
        };
    }

    private function cleanupExpiredSessions(): void
    {
        $cleanupAfterHours = config('flamingdragon.execution.cleanup_after_hours', 24);
        $cutoff = now()->subHours($cleanupAfterHours);

        // Mark stale "running" sessions as timed out
        $stale = AgentSession::where('status', AgentStatus::Running)
            ->where('started_at', '<', now()->subMinutes(60))
            ->get();

        foreach ($stale as $session) {
            $session->update(['status' => AgentStatus::Timeout, 'completed_at' => now()]);
            $this->line("Marked stale session as timeout: {$session->session_uuid}");
        }

        // Clean up old session directories
        $sessionDir = config('flamingdragon.execution.session_dir');
        if (is_dir($sessionDir)) {
            foreach (scandir($sessionDir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path  = $sessionDir . DIRECTORY_SEPARATOR . $item;
                $mtime = filemtime($path);
                if ($mtime && $mtime < $cutoff->timestamp) {
                    $this->removeDirectory($path);
                    $this->line("Cleaned session dir: {$item}");
                }
            }
        }

        $this->info('Cleaned up expired sessions.');
    }

    private function checkQueueHealth(): void
    {
        $pending = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $this->info("Queue: {$pending} pending jobs.");
        Log::info('[FdHeartbeat] Queue health check.', ['pending_jobs' => $pending]);
    }

    private function memorySweep(): void
    {
        $count = $this->memoryService->sweep();
        $this->info("Memory sweep: {$count} expired entries deleted.");
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
