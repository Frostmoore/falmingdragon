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

        // Code-generation commands always use Anthropic + Opus regardless of defaults
        $codeCommands = ['generateskill', 'generatetool', 'generateskilltool', 'create_skill', 'write_file', 'run_script'];
        if (in_array($command->commandName, $codeCommands, true)) {
            $providerName = 'anthropic';
            $modelName    = 'claude-opus-4-6';
        }

        $this->sessions->markRunning($session, $providerName, $modelName);

        $sandbox = new SessionSandbox($session->session_uuid);
        $sandbox->initialize();

        $toolExecutor = new ToolExecutor($sandbox);

        // Load relevant memories — semantic search when embeddings are available
        $memories = $this->memoryService->getContext(query: $session->raw_input, limit: 10);

        // Load skill if required
        $skill = $definition->skill_required
            ? $this->skillManager->findByName($definition->skill_required)
            : null;

        // Build tool definitions for LLM (used both for API call and system prompt)
        $grantedTools = $definition->tools_allowed ?? [];
        $toolDefs     = $this->buildToolDefinitions($grantedTools);

        // Assemble system prompt
        $systemPrompt = $this->promptBuilder->build(
            command:         $definition,
            grantedTools:    $grantedTools,
            memories:        $memories,
            skill:           $skill,
            maxToolCalls:    config('flamingdragon.security.max_tool_calls_per_session', 50),
            timeoutSeconds:  $definition->timeout_seconds,
            toolDefinitions: $toolDefs,
        );

        $stepNumber   = 0;

        // Build initial conversation
        $messages = [
            ['role' => 'user', 'content' => $session->raw_input],
        ];
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

                // Log LLM step (content may be an array of blocks — serialize to string for log)
                $lastContent = end($messages)['content'] ?? '';
                $this->logStep(
                    sessionId:  $session->id,
                    stepNumber: $stepNumber,
                    actionType: ActionType::LlmCall,
                    input:      is_array($lastContent) ? json_encode($lastContent) : (string) $lastContent,
                    output:     $llmResponse->content,
                    tokens:     $llmResponse->totalTokens(),
                    duration:   $llmDuration,
                );

                // If no tool calls — we have the final answer
                if (! $llmResponse->hasToolCalls()) {
                    $finalAnswer = $llmResponse->content;
                    break;
                }

                // Append assistant message.
                // If rawBlocks are available (Anthropic), use them directly — the API requires
                // the exact tool_use blocks to be echoed back in the next turn.
                // Normalize: empty PHP arrays inside tool_use.input must be cast to stdClass
                // so json_encode produces {} (object) not [] (array) as Anthropic requires.
                $messages[] = [
                    'role'    => 'assistant',
                    'content' => ! empty($llmResponse->rawBlocks)
                        ? $this->normalizeRawBlocks($llmResponse->rawBlocks)
                        : ($llmResponse->content ?: null),
                ];

                // Execute each tool call and collect results.
                // Anthropic requires all tool_result blocks in a SINGLE user message.
                $toolResultBlocks = [];

                foreach ($llmResponse->toolCalls as $toolCall) {
                    $toolName = $toolCall['name'];
                    $toolArgs = $toolCall['arguments'];
                    $toolId   = $toolCall['id'] ?? null;

                    // Validate tool is granted for this session
                    if (! in_array($toolName, $grantedTools, true)) {
                        $toolResult = "Tool '{$toolName}' is not granted for this session.";
                    } else {
                        $stepNumber++;
                        $result     = $toolExecutor->execute($toolName, $toolArgs, $session->id, $stepNumber);
                        $toolResult = $result['output'];
                    }

                    // Build result block: Anthropic format if we have tool IDs, plain text otherwise
                    if ($toolId !== null) {
                        $toolResultBlocks[] = [
                            'type'        => 'tool_result',
                            'tool_use_id' => $toolId,
                            'content'     => $toolResult,
                        ];
                    } else {
                        // Fallback for providers without tool IDs (OpenAI-style)
                        $toolResultBlocks[] = "Tool '{$toolName}' result:\n{$toolResult}";
                    }
                }

                // Append all tool results as a single user message
                if (! empty($toolResultBlocks)) {
                    // If all blocks are arrays (Anthropic tool_result format), use array content
                    $allFormatted = array_reduce($toolResultBlocks, fn($c, $b) => $c && is_array($b), true);
                    $messages[] = [
                        'role'    => 'user',
                        'content' => $allFormatted
                            ? $toolResultBlocks
                            : implode("\n\n", array_map(
                                fn($b) => is_array($b) ? ($b['content'] ?? '') : $b,
                                $toolResultBlocks
                            )),
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
     * Normalize raw Anthropic content blocks for safe re-submission.
     * PHP deserializes JSON {} as [] (empty array). Anthropic requires {} for tool_use.input.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRawBlocks(array $blocks): array
    {
        return array_map(function (array $block): array {
            if (($block['type'] ?? '') === 'tool_use') {
                $input = $block['input'] ?? [];
                // Cast empty array to stdClass so json_encode produces {} not []
                $block['input'] = empty($input) ? new \stdClass() : $input;
            }
            return $block;
        }, $blocks);
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
            'file_read'          => ['description' => 'Read a file.', 'properties' => ['path' => ['type' => 'string']]],
            'file_write'         => ['description' => 'Write content to a file.', 'properties' => ['path' => ['type' => 'string'], 'content' => ['type' => 'string']]],
            'file_list'          => ['description' => 'List directory contents.', 'properties' => ['path' => ['type' => 'string']]],
            'file_delete'        => ['description' => 'Delete a file.', 'properties' => ['path' => ['type' => 'string']]],
            'file_search'        => ['description' => 'Search for files by name or content.', 'properties' => ['query' => ['type' => 'string'], 'path' => ['type' => 'string'], 'mode' => ['type' => 'string', 'description' => '"name" or "content"']]],
            'http_get'           => ['description' => 'HTTP GET request.', 'properties' => ['url' => ['type' => 'string']]],
            'http_post'          => ['description' => 'HTTP POST request.', 'properties' => ['url' => ['type' => 'string'], 'payload' => ['type' => 'object']]],
            'json_api'           => ['description' => 'Call a JSON API.', 'properties' => ['url' => ['type' => 'string'], 'method' => ['type' => 'string'], 'payload' => ['type' => 'object'], 'headers' => ['type' => 'object']]],
            'web_search'         => ['description' => 'Search the web via DuckDuckGo.', 'properties' => ['query' => ['type' => 'string'], 'limit' => ['type' => 'integer']]],
            'weather'            => ['description' => 'Get current weather for a location.', 'properties' => ['location' => ['type' => 'string', 'description' => 'City name or coordinates']]],
            'summarize_url'      => ['description' => 'Fetch a URL and summarize its content.', 'properties' => ['url' => ['type' => 'string']]],
            'send_email'         => ['description' => 'Send an email.', 'properties' => ['to' => ['type' => 'string'], 'subject' => ['type' => 'string'], 'body' => ['type' => 'string']]],
            'image_generate'     => ['description' => 'Generate an image with DALL-E 3.', 'properties' => ['prompt' => ['type' => 'string'], 'size' => ['type' => 'string']]],
            'db_query'           => ['description' => 'Run a read-only SELECT query.', 'properties' => ['sql' => ['type' => 'string']]],
            'cron_list'          => ['description' => 'List scheduled tasks.', 'properties' => []],
            'memory_read'        => ['description' => 'Read from memory.', 'properties' => ['key' => ['type' => 'string'], 'namespace' => ['type' => 'string']]],
            'memory_write'       => ['description' => 'Write to memory.', 'properties' => ['key' => ['type' => 'string'], 'value' => ['type' => 'string'], 'namespace' => ['type' => 'string']]],
            'telegram_send'      => ['description' => 'Send a Telegram message to the user.', 'properties' => ['text' => ['type' => 'string']]],
            'skill_read'         => ['description' => 'Read a skill definition.', 'properties' => ['name' => ['type' => 'string']]],
            'process_status'     => ['description' => 'List running processes.', 'properties' => []],
            'laravel_artisan'    => ['description' => 'Run an artisan command.', 'properties' => ['command' => ['type' => 'string']]],
            'git_operation'      => ['description' => 'Git operation (pull/status/log/diff/fetch).', 'properties' => ['operation' => ['type' => 'string'], 'path' => ['type' => 'string']]],
            'composer_operation'     => ['description' => 'Composer operation.', 'properties' => ['operation' => ['type' => 'string'], 'path' => ['type' => 'string']]],
            'npm_operation'          => ['description' => 'NPM operation.', 'properties' => ['operation' => ['type' => 'string'], 'script' => ['type' => 'string'], 'path' => ['type' => 'string']]],
            // Gmail
            'gmail_list'      => ['description' => 'List Gmail messages. Use query for filtering (e.g. "is:unread in:inbox").', 'properties' => ['query' => ['type' => 'string'], 'max_results' => ['type' => 'integer'], 'label' => ['type' => 'string']]],
            'gmail_read'      => ['description' => 'Read a full Gmail message by ID. Also marks it as read.', 'properties' => ['message_id' => ['type' => 'string']]],
            'gmail_send'      => ['description' => 'Compose and send an email via Gmail.', 'properties' => ['to' => ['type' => 'string'], 'subject' => ['type' => 'string'], 'body' => ['type' => 'string'], 'cc' => ['type' => 'string'], 'bcc' => ['type' => 'string']]],
            'gmail_search'    => ['description' => 'Search Gmail with a query string (Gmail search syntax).', 'properties' => ['query' => ['type' => 'string'], 'max_results' => ['type' => 'integer']]],
            'gmail_mark_read' => ['description' => 'Mark a Gmail message as read by ID.', 'properties' => ['message_id' => ['type' => 'string']]],
            'gmail_trash'     => ['description' => 'Move a Gmail message to trash by ID.', 'properties' => ['message_id' => ['type' => 'string']]],
            // Google Calendar
            'google_calendar_list'   => ['description' => 'List upcoming Google Calendar events.', 'properties' => ['days_ahead' => ['type' => 'integer'], 'max_results' => ['type' => 'integer']]],
            'google_calendar_create' => ['description' => 'Create a Google Calendar event.', 'properties' => ['title' => ['type' => 'string'], 'start' => ['type' => 'string', 'description' => 'ISO 8601'], 'end' => ['type' => 'string', 'description' => 'ISO 8601'], 'description' => ['type' => 'string'], 'location' => ['type' => 'string'], 'timezone' => ['type' => 'string']]],
            'google_calendar_delete' => ['description' => 'Delete a Google Calendar event by ID.', 'properties' => ['event_id' => ['type' => 'string']]],
            // Todos
            'todo_create'   => ['description' => 'Create a new todo item.', 'properties' => ['title' => ['type' => 'string'], 'list_name' => ['type' => 'string'], 'priority' => ['type' => 'string'], 'due_at' => ['type' => 'string'], 'notes' => ['type' => 'string']]],
            'todo_list'     => ['description' => 'List todos. Use list_name="all" for all lists.', 'properties' => ['list_name' => ['type' => 'string'], 'show_done' => ['type' => 'boolean']]],
            'todo_complete' => ['description' => 'Mark a todo as done by ID.', 'properties' => ['id' => ['type' => 'integer']]],
            'todo_delete'   => ['description' => 'Delete a todo by ID.', 'properties' => ['id' => ['type' => 'integer']]],
            // Shopping list
            'shopping_add'    => ['description' => 'Add item to a shopping list.', 'properties' => ['name' => ['type' => 'string'], 'list_name' => ['type' => 'string'], 'category' => ['type' => 'string'], 'quantity' => ['type' => 'number'], 'unit' => ['type' => 'string']]],
            'shopping_items'  => ['description' => 'List shopping items.', 'properties' => ['list_name' => ['type' => 'string'], 'show_bought' => ['type' => 'boolean']]],
            'shopping_bought' => ['description' => 'Mark a shopping item as bought by ID.', 'properties' => ['id' => ['type' => 'integer']]],
            'shopping_clear'  => ['description' => 'Remove all bought items from a shopping list.', 'properties' => ['list_name' => ['type' => 'string']]],
            // Messaging & social
            'whatsapp_send'   => ['description' => 'Send a WhatsApp message via Meta Cloud API.', 'properties' => ['to' => ['type' => 'string', 'description' => 'Phone in international format'], 'message' => ['type' => 'string'], 'template' => ['type' => 'string']]],
            'facebook_post'   => ['description' => 'Post to a Facebook Page.', 'properties' => ['message' => ['type' => 'string'], 'link' => ['type' => 'string'], 'image_url' => ['type' => 'string']]],
            'facebook_feed'   => ['description' => 'Read recent Facebook Page posts.', 'properties' => ['limit' => ['type' => 'integer']]],
            'instagram_post'  => ['description' => 'Post an image to Instagram Business.', 'properties' => ['image_url' => ['type' => 'string'], 'caption' => ['type' => 'string']]],
            // Document generation
            'generate_qr'   => ['description' => 'Generate a QR code image URL for any text or URL.', 'properties' => ['content' => ['type' => 'string'], 'size' => ['type' => 'integer']]],
            'generate_pdf'  => ['description' => 'Generate a PDF from HTML content. Returns download URL.', 'properties' => ['filename' => ['type' => 'string'], 'html' => ['type' => 'string'], 'title' => ['type' => 'string']]],
            'generate_docx' => ['description' => 'Generate a Word .docx document. Returns download URL.', 'properties' => ['filename' => ['type' => 'string'], 'title' => ['type' => 'string'], 'content' => ['type' => 'array', 'description' => 'Array of {type, text} blocks']]],
            'generate_xlsx' => ['description' => 'Generate an Excel .xlsx spreadsheet. Returns download URL.', 'properties' => ['filename' => ['type' => 'string'], 'sheet_name' => ['type' => 'string'], 'headers' => ['type' => 'array'], 'rows' => ['type' => 'array']]],
            // Vision / image
            'analyze_image'       => ['description' => 'Analyze or describe an image using GPT-4o vision. Accepts a local file path or public URL.', 'properties' => ['image_path' => ['type' => 'string', 'description' => 'Absolute local path or public URL of the image'], 'question' => ['type' => 'string', 'description' => 'What to ask about the image (default: describe it)']]],
            'generate_image'      => ['description' => 'Generate an image with DALL-E 3. Returns the public image URL.', 'properties' => ['prompt' => ['type' => 'string', 'description' => 'Text description of the image to generate'], 'size' => ['type' => 'string', 'description' => '1024x1024 | 1792x1024 | 1024x1792 (default: 1024x1024)'], 'quality' => ['type' => 'string', 'description' => 'standard | hd (default: standard)']]],
            'send_telegram_image' => ['description' => 'Send an image (URL or local path) to the Telegram user.', 'properties' => ['image' => ['type' => 'string', 'description' => 'Public URL or absolute local path'], 'caption' => ['type' => 'string', 'description' => 'Optional caption for the image']]],
            // Audio
            'transcribe_audio'    => ['description' => 'Transcribe an audio file to text using OpenAI Whisper.', 'properties' => ['audio_path' => ['type' => 'string', 'description' => 'Absolute local path to the audio file (.ogg, .mp3, .wav, etc.)'], 'language' => ['type' => 'string', 'description' => 'Optional ISO-639-1 language code (e.g. "it", "en")']]],
            'generate_audio'      => ['description' => 'Generate speech from text using OpenAI TTS. Returns the local path to the generated MP3.', 'properties' => ['text' => ['type' => 'string', 'description' => 'The text to convert to speech'], 'voice' => ['type' => 'string', 'description' => 'alloy | echo | fable | onyx | nova | shimmer (default: nova)'], 'speed' => ['type' => 'number', 'description' => 'Playback speed 0.25–4.0 (default: 1.0)']]],
            'send_telegram_voice' => ['description' => 'Send an audio file as a voice/audio message to the Telegram user.', 'properties' => ['audio_path' => ['type' => 'string', 'description' => 'Absolute local path to the audio file'], 'caption' => ['type' => 'string', 'description' => 'Optional caption']]],
        ];

        $result = [];
        foreach ($grantedTools as $toolName) {
            if (! isset($definitions[$toolName])) {
                continue;
            }
            $def        = $definitions[$toolName];
            // Empty array serialises as JSON [] (invalid for Anthropic), must be {} object
            $properties = empty($def['properties']) ? new \stdClass() : $def['properties'];
            $result[] = [
                'name'         => $toolName,
                'description'  => $def['description'],
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => $properties,
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
