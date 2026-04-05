<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\ExecutionMode;
use App\Jobs\ExecuteAgentJob;
use App\Models\AgentSession;
use App\Services\Command\ParsedCommand;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether to run an agent synchronously or dispatch it to the queue.
 */
class AgentSpawner
{
    public function __construct(
        private readonly AgentOrchestrator $orchestrator,
        private readonly SessionManager    $sessions,
    ) {}

    /**
     * Spawn an agent for the given command.
     * Returns the result summary string (sync) or a "queued" message (async).
     *
     * @param  ParsedCommand  $command
     * @param  int|null       $telegramMessageId
     * @param  int|null       $telegramChatId
     * @return string
     */
    public function spawn(
        ParsedCommand $command,
        ?int $telegramMessageId = null,
        ?int $telegramChatId = null,
    ): string {
        $maxConcurrent = config('flamingdragon.execution.max_concurrent_agents', 3);
        $running       = $this->sessions->countRunning();

        if ($running >= $maxConcurrent) {
            Log::warning('[AgentSpawner] Max concurrent agents reached.', [
                'running' => $running,
                'max'     => $maxConcurrent,
            ]);
            return "System is busy ({$running} agents running). Please try again shortly.";
        }

        $session = $this->sessions->create($command, $telegramMessageId);

        if ($command->executionMode === ExecutionMode::Async) {
            return $this->spawnAsync($session, $command, $telegramChatId);
        }

        return $this->spawnSync($session, $command, $telegramChatId);
    }

    private function spawnSync(
        AgentSession $session,
        ParsedCommand $command,
        ?int $chatId,
    ): string {
        try {
            return $this->orchestrator->run($session, $command, $chatId);
        } catch (\Throwable $e) {
            Log::error('[AgentSpawner] Sync execution failed.', ['error' => $e->getMessage()]);
            $this->sessions->markFailed($session, $e->getMessage());
            return 'Execution failed. Check the logs for details.';
        }
    }

    private function spawnAsync(
        AgentSession $session,
        ParsedCommand $command,
        ?int $chatId,
    ): string {
        ExecuteAgentJob::dispatch($session->id, $command->commandName, $chatId)
            ->onQueue('default')
            ->timeout($command->definition->timeout_seconds);

        Log::info('[AgentSpawner] Agent queued.', [
            'session' => $session->session_uuid,
            'command' => $command->commandName,
        ]);

        return "Command '{$command->commandName}' queued. Session: `{$session->session_uuid}`";
    }
}
