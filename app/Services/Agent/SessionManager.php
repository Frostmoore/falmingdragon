<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AgentStatus;
use App\Enums\ExecutionMode;
use App\Models\AgentSession;
use App\Services\Command\ParsedCommand;
use Illuminate\Support\Str;

/**
 * Creates and manages agent session records.
 */
class SessionManager
{
    /**
     * Create a new session record for an incoming command.
     *
     * @param  ParsedCommand   $command
     * @param  int|null        $telegramMessageId
     * @return AgentSession
     */
    public function create(ParsedCommand $command, ?int $telegramMessageId = null): AgentSession
    {
        $mode = $command->executionMode === \App\Enums\ExecutionMode::Auto
            ? \App\Enums\ExecutionMode::Sync
            : $command->executionMode;

        return AgentSession::create([
            'session_uuid'        => Str::uuid()->toString(),
            'telegram_message_id' => $telegramMessageId,
            'command'             => $command->commandName,
            'raw_input'           => $command->naturalLanguageInput ?? $command->commandName,
            'status'              => AgentStatus::Queued,
            'execution_mode'      => $mode,
            'tools_granted'       => $command->definition->tools_allowed ?? [],
            'timeout_seconds'     => $command->definition->timeout_seconds,
        ]);
    }

    /**
     * Transition a session to "running" state.
     *
     * @param  AgentSession  $session
     * @param  string        $llmProvider
     * @param  string        $llmModel
     */
    public function markRunning(AgentSession $session, string $llmProvider, string $llmModel): void
    {
        $session->update([
            'status'       => AgentStatus::Running,
            'llm_provider' => $llmProvider,
            'llm_model'    => $llmModel,
            'started_at'   => now(),
        ]);
    }

    /**
     * Transition a session to "completed" state.
     *
     * @param  AgentSession  $session
     * @param  string        $resultSummary
     * @param  string        $resultFull
     * @param  int           $tokensIn
     * @param  int           $tokensOut
     */
    public function markCompleted(
        AgentSession $session,
        string $resultSummary,
        string $resultFull,
        int $tokensIn = 0,
        int $tokensOut = 0,
    ): void {
        $session->update([
            'status'         => AgentStatus::Completed,
            'result_summary' => $resultSummary,
            'result_full'    => $resultFull,
            'tokens_input'   => $session->tokens_input + $tokensIn,
            'tokens_output'  => $session->tokens_output + $tokensOut,
            'completed_at'   => now(),
        ]);
    }

    /**
     * Transition a session to "failed" state.
     *
     * @param  AgentSession  $session
     * @param  string        $errorMessage
     */
    public function markFailed(AgentSession $session, string $errorMessage): void
    {
        $session->update([
            'status'        => AgentStatus::Failed,
            'error_message' => $errorMessage,
            'completed_at'  => now(),
        ]);
    }

    /**
     * Transition a session to "cancelled" state.
     *
     * @param  AgentSession  $session
     */
    public function markCancelled(AgentSession $session): void
    {
        $session->update([
            'status'       => AgentStatus::Cancelled,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a session as timed out.
     *
     * @param  AgentSession  $session
     */
    public function markTimeout(AgentSession $session): void
    {
        $session->update([
            'status'       => AgentStatus::Timeout,
            'completed_at' => now(),
        ]);
    }

    /**
     * Count sessions currently in running state.
     */
    public function countRunning(): int
    {
        return AgentSession::where('status', AgentStatus::Running)->count();
    }

    /**
     * Find a session by UUID.
     */
    public function findByUuid(string $uuid): ?AgentSession
    {
        return AgentSession::where('session_uuid', $uuid)->first();
    }
}
