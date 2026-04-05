<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AgentSession;
use App\Services\Agent\AgentOrchestrator;
use App\Services\Agent\SessionManager;
use App\Services\Command\CommandRouter;
use App\Services\Telegram\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteAgentJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Maximum execution time in seconds (overridden per-session).
     */
    public int $timeout = 600;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        private readonly int    $sessionId,
        private readonly string $commandName,
        private readonly ?int   $chatId = null,
    ) {}

    public function handle(
        AgentOrchestrator $orchestrator,
        SessionManager    $sessions,
        CommandRouter     $router,
        TelegramService   $telegram,
    ): void {
        $session = AgentSession::find($this->sessionId);

        if ($session === null) {
            Log::error('[ExecuteAgentJob] Session not found.', ['session_id' => $this->sessionId]);
            return;
        }

        // Respect per-session timeout
        $this->timeout = $session->timeout_seconds;

        $parsedCommand = $router->route($this->commandName, [], $session->raw_input);

        if ($parsedCommand === null) {
            $sessions->markFailed($session, "Command '{$this->commandName}' no longer in allow-list.");
            if ($this->chatId) {
                $telegram->sendMessage($this->chatId, "Command '{$this->commandName}' is no longer available.");
            }
            return;
        }

        try {
            $result = $orchestrator->run($session, $parsedCommand, $this->chatId);

            if ($this->chatId) {
                $telegram->sendLongMessage($this->chatId, $result);
            }
        } catch (Throwable $e) {
            Log::error('[ExecuteAgentJob] Execution failed.', [
                'session' => $session->session_uuid,
                'error'   => $e->getMessage(),
            ]);

            $sessions->markFailed($session, $e->getMessage());

            if ($this->chatId) {
                $telegram->sendMessage(
                    $this->chatId,
                    "Command '{$this->commandName}' failed. Please check the dashboard for details."
                );
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[ExecuteAgentJob] Job failed.', [
            'session_id' => $this->sessionId,
            'command'    => $this->commandName,
            'error'      => $exception->getMessage(),
        ]);

        $session = AgentSession::find($this->sessionId);
        if ($session) {
            app(SessionManager::class)->markFailed($session, $exception->getMessage());
        }
    }
}
