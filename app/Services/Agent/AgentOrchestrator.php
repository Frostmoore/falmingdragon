<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\ActionType;
use App\Models\AgentSession;
use App\Models\ExecutionLog;
use App\Services\Command\ParsedCommand;
use App\Services\Llm\LlmRouter;
use App\Services\Llm\PromptBuilder;
use App\Services\Memory\MemoryService;
use App\Services\Security\SessionSandbox;
use App\Services\Skill\SkillManager;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The main agentic loop. Receives a parsed command, orchestrates tool-use cycles,
 * and returns the final result.
 */
class AgentOrchestrator
{
    public function __construct(
        private readonly LlmRouter      $llmRouter,
        private readonly PromptBuilder  $promptBuilder,
        private readonly MemoryService  $memoryService,
        private readonly SkillManager   $skillManager,
        private readonly SessionManager $sessions,
        private readonly TelegramService $telegram,
    ) {}

    /**
     * Run the agent loop for a given session and command.
     *
     * @param  AgentSession   $session
     * @param  ParsedCommand  $command
     * @param  int|null       $chatId  For sending progress messages back to Telegram.
     * @return string  Final result summary.
     */
    public function run(
        AgentSession $session,
        ParsedCommand $command,
        ?int $chatId = null,
    ): string {
        $definition     = $command->definition;
        $providerName   = $definition->llm_provider_override
            ?? config('flamingdragon.llm.default_provider', 'anthropic');
        $modelName      = $definition->llm_model_override
            ?? config('flamingdragon.llm.default_model');

        $this->sessions->markRunning($session, $providerName, $modelName);

        $sandbox = new SessionSandbox($session->session_uuid);
        $sandbox->initialize();

        $toolExecutor = new ToolExecutor($sandbox);

        // Load relevant memories
        $memories = $this->memoryService->getContext(limit: 10);

        // Load skill if required
        $skill = $definition->skill_required
            ? $this->skillManager->findByName($definition->skill_required)
            : null;

        // Assemble system prompt
        $grantedTools = $definition->tools_allowed ?? [];
        $systemPrompt = $this->promptBuilder->build(
            command:        $definition,
            grantedTools:   $grantedTools,
            memories:       $memories,
            skill:          $skill,
            maxToolCalls:   config('flamingdragon.security.max_tool_calls_per_session', 50),
            timeoutSeconds: $definition->timeout_seconds,
        );

        // Build initial conversation
        $messages = [
            ['role' => 'user', 'content' => $session->raw_input],
        ];

        // Build tool definitions for LLM
        $toolDefs     = $this->buildToolDefinitions($grantedTools);
        $stepNumber   = 0;
        $totalInput   = 0;
        $totalOutput  = 0;
        $finalAnswer  = '';

        try {
            $maxSteps = config('flamingdragon.security.max_tool_calls_per_session', 50);

            while ($stepNumber < $maxSteps) {
                $stepNumber++;

                // LLM call
                $llmStart    = microtime(true);
                $llmResponse = $this->llmRouter->chat($messages, $toolDefs, [
                    'provider' => $providerName,
                    'model'    => $modelName,
                    'system'   => $systemPrompt,
                ]);
                $llmDuration = (int) ((microtime(true) - $llmStart) * 1000);

                $totalInput  += $llmResponse->inputTokens;
                $totalOutput += $llmResponse->outputTokens;

                // Log LLM step
                $this->logStep(
                    sessionId:  $session->id,
                    stepNumber: $stepNumber,
                    actionType: ActionType::LlmCall,
                    input:      end($messages)['content'] ?? '',
                    output:     $llmResponse->content,
                    tokens:     $llmResponse->totalTokens(),
                    duration:   $llmDuration,
                );

                // If no tool calls — we have the final answer
                if (! $llmResponse->hasToolCalls()) {
                    $finalAnswer = $llmResponse->content;
                    break;
                }

                // Append assistant message
                $messages[] = [
                    'role'    => 'assistant',
                    'content' => $llmResponse->content ?: null,
                ];

                // Execute each tool call
                foreach ($llmResponse->toolCalls as $toolCall) {
                    $toolName = $toolCall['name'];
                    $toolArgs = $toolCall['arguments'];

                    // Validate tool is granted for this session
                    if (! in_array($toolName, $grantedTools, true)) {
                        $toolResult = "Tool '{$toolName}' is not granted for this session.";
                    } else {
                        $stepNumber++;
                        $result     = $toolExecutor->execute($toolName, $toolArgs, $session->id, $stepNumber);
                        $toolResult = $result['output'];
                    }

                    // Append tool result to conversation
                    $messages[] = [
                        'role'    => 'user',
                        'content' => "Tool '{$toolName}' result:\n{$toolResult}",
                    ];
                }

                if ($llmResponse->isComplete()) {
                    break;
                }
            }

            if ($finalAnswer === '') {
                $finalAnswer = 'Agent completed without producing a final answer.';
            }

            // Store a summary memory of this execution
            $this->memoryService->remember(
                namespace: 'session_history',
                key:       $session->session_uuid,
                value:     "Command: {$command->commandName} — Result: " . substr($finalAnswer, 0, 200),
                source:    "session:{$session->session_uuid}",
                expiresInSeconds: 7 * 86400, // 7 days
            );

            $this->sessions->markCompleted(
                session:       $session,
                resultSummary: substr($finalAnswer, 0, 500),
                resultFull:    $finalAnswer,
                tokensIn:      $totalInput,
                tokensOut:     $totalOutput,
            );

            return $finalAnswer;
        } catch (Throwable $e) {
            Log::error('[AgentOrchestrator] Execution failed.', [
                'session' => $session->session_uuid,
                'error'   => $e->getMessage(),
            ]);
            $this->sessions->markFailed($session, $e->getMessage());
            return 'Execution failed. Please check the system logs.';
        }
    }

    /**
     * Build tool definition schemas for the Large Language Model.
     *
     * @param  array<string>  $grantedTools
     * @return array<int, array<string, mixed>>
     */
    private function buildToolDefinitions(array $grantedTools): array
    {
        $definitions = [
            'bash'               => ['description' => 'Execute a shell command.', 'properties' => ['command' => ['type' => 'string', 'description' => 'The shell command to run.']]],
            'file_read'          => ['description' => 'Read a file.', 'properties' => ['path' => ['type' => 'string', 'description' => 'Absolute path to the file.']]],
            'file_write'         => ['description' => 'Write a file.', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']]],
            'file_list'          => ['description' => 'List directory contents.', 'properties' => ['path' => ['type' => 'string', 'description' => 'Directory path.']]],
            'http_get'           => ['description' => 'HTTP GET request.', 'properties' => ['url' => ['type' => 'string']]],
            'http_post'          => ['description' => 'HTTP POST request.', 'properties' => ['url' => ['type' => 'string'], 'payload' => ['type' => 'object']]],
            'memory_read'        => ['description' => 'Read from memory.', 'properties' => ['key' => ['type' => 'string'], 'namespace' => ['type' => 'string']]],
            'memory_write'       => ['description' => 'Write to memory.', 'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string'], 'namespace' => ['type' => 'string']]],
            'telegram_send'      => ['description' => 'Send a Telegram message.', 'properties' => ['text' => ['type' => 'string']]],
            'skill_read'         => ['description' => 'Read a skill definition.', 'properties' => ['name' => ['type' => 'string']]],
            'process_status'     => ['description' => 'List running processes.', 'properties' => []],
            'laravel_artisan'    => ['description' => 'Run artisan command.', 'properties' => ['command' => ['type' => 'string']]],
            'git_operation'      => ['description' => 'Git operation.', 'properties' => ['operation' => ['type' => 'string'], 'path' => ['type' => 'string']]],
            'composer_operation' => ['description' => 'Composer operation.', 'properties' => ['operation' => ['type' => 'string'], 'path' => ['type' => 'string']]],
            'npm_operation'      => ['description' => 'NPM operation.', 'properties' => ['operation' => ['type' => 'string'], 'script' => ['type' => 'string'], 'path' => ['type' => 'string']]],
        ];

        $result = [];
        foreach ($grantedTools as $toolName) {
            if (! isset($definitions[$toolName])) {
                continue;
            }
            $def = $definitions[$toolName];
            $result[] = [
                'name'         => $toolName,
                'description'  => $def['description'],
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => $def['properties'],
                ],
            ];
        }

        return $result;
    }

    private function logStep(
        int $sessionId,
        int $stepNumber,
        ActionType $actionType,
        ?string $input,
        ?string $output,
        int $tokens = 0,
        int $duration = 0,
    ): void {
        try {
            ExecutionLog::create([
                'session_id'  => $sessionId,
                'step_number' => $stepNumber,
                'action_type' => $actionType,
                'tool_name'   => null,
                'input_data'  => $input,
                'output_data' => substr((string) $output, 0, 65535),
                'tokens_used' => $tokens,
                'duration_ms' => $duration,
                'created_at'  => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('[AgentOrchestrator] Log step failed.', ['error' => $e->getMessage()]);
        }
    }
}
