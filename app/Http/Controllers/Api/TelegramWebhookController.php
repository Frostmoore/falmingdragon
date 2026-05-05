<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agent\AgentSpawner;
use App\Services\Command\CommandRouter;
use App\Services\Command\ParsedCommand;
use App\Services\Generator\GeneratorService;
use App\Services\Telegram\TelegramParser;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramParser   $parser,
        private readonly TelegramService  $telegram,
        private readonly CommandRouter    $router,
        private readonly AgentSpawner     $spawner,
        private readonly GeneratorService $generator,
    ) {}

    /**
     * Handle incoming Telegram webhook update.
     * Auth (chat ID validation) is handled by TelegramAuthMiddleware upstream.
     */
    public function handle(Request $request): Response
    {
        try {
            $update    = $request->json()->all();
            $chatId    = $this->parser->extractChatId($update);
            $messageId = $this->parser->extractMessageId($update);

            Log::info('[Webhook] handle() entered.', [
                'chat_id'      => $chatId,
                'message_id'   => $messageId,
                'update_keys'  => array_keys($update),
                'message_keys' => array_keys($update['message'] ?? []),
            ]);

            if ($chatId === null) {
                Log::warning('[Webhook] chat_id is null — ignoring.');
                return response('', 200);
            }

            // --- Handle photo messages ---
            $photo = $this->parser->extractPhoto($update);
            if ($photo !== null) {
                Log::info('[Webhook] Photo message detected.', ['file_id' => $photo['file_id']]);
                $this->handlePhoto($chatId, $messageId, $photo, $this->parser->extractCaption($update));
                return response('', 200);
            }

            // --- Handle voice messages ---
            $voice = $this->parser->extractVoice($update);
            if ($voice !== null) {
                Log::info('[Webhook] Voice message detected.', ['file_id' => $voice['file_id']]);
                $this->handleVoice($chatId, $messageId, $voice);
                return response('', 200);
            }

            $text = $this->parser->extractText($update);

            Log::info('[Webhook] Text message.', ['text' => mb_substr((string) $text, 0, 100)]);

            if ($text === null) {
                Log::warning('[Webhook] No text — ignoring.');
                return response('', 200);
            }

            $parsed = $this->parser->parseCommand($text);

            // --- Handle active generator conversation (takes priority) ---
            if ($this->generator->isActive($chatId)) {
                // /generateskill|tool can restart — otherwise feed to generator
                if (! in_array($parsed['command'], ['generateskill','generatetool','generateskilltool'], true)) {
                    $this->generator->handleMessage($chatId, $text);
                    return response('', 200);
                }
            }

            // --- Handle generator start commands ---
            if ($parsed['command'] === 'generateskill') {
                $this->generator->start($chatId, 'skill');
                return response('', 200);
            }
            if ($parsed['command'] === 'generatetool') {
                $this->generator->start($chatId, 'tool');
                return response('', 200);
            }
            if ($parsed['command'] === 'generateskilltool') {
                $this->generator->start($chatId, 'skilltool');
                return response('', 200);
            }

            // --- Handle built-in meta-commands ---
            if ($parsed['command'] === 'confirm') {
                return $this->handleConfirm($chatId, $messageId);
            }

            if ($parsed['command'] === 'deny') {
                return $this->handleDeny($chatId);
            }

            // --- Natural language: pass to LLM for interpretation ---
            if ($parsed['command'] === null) {
                $commandName = $this->interpretNaturalLanguage($text, $chatId);
                if ($commandName === null) {
                    $this->telegram->sendMessage($chatId, 'I\'m not sure what you want to do. Send /help to see available commands.');
                    return response('', 200);
                }
                $parsed['command'] = $commandName;
            }

            // --- Route to allow-list ---
            $parsedCommand = $this->router->route(
                $parsed['command'],
                $parsed['args'],
                $parsed['natural_language'],
            );

            if ($parsedCommand === null) {
                $this->telegram->sendMessage(
                    $chatId,
                    "Command not recognized. Use /help to see available commands."
                );
                return response('', 200);
            }

            // --- Dangerous command: require confirmation ---
            if ($parsedCommand->requiresConfirmation) {
                Cache::put(
                    "fd_pending_command:{$chatId}",
                    ['command' => $parsedCommand->commandName, 'args' => $parsedCommand->arguments],
                    now()->addMinutes(5)
                );

                $this->telegram->sendMessage(
                    $chatId,
                    "⚠️ <b>Dangerous command:</b> <code>{$parsedCommand->commandName}</code>\n\n"
                    . "Send /confirm to execute or /deny to cancel."
                );
                return response('', 200);
            }

            // --- Execute ---
            $result = $this->spawner->spawn($parsedCommand, $messageId, $chatId);
            $this->telegram->sendLongMessage($chatId, $result);
        } catch (Throwable $e) {
            Log::error('[TelegramWebhookController] Unhandled exception.', [
                'error' => $e->getMessage(),
            ]);
            // Never expose raw errors to Telegram
        }

        return response('', 200);
    }

    private function handleConfirm(int $chatId, ?int $messageId): Response
    {
        $pending = Cache::get("fd_pending_command:{$chatId}");

        if ($pending === null) {
            $this->telegram->sendMessage($chatId, 'No pending command to confirm.');
            return response('', 200);
        }

        Cache::forget("fd_pending_command:{$chatId}");

        $parsedCommand = $this->router->route($pending['command'], $pending['args'] ?? []);

        if ($parsedCommand === null) {
            $this->telegram->sendMessage($chatId, 'The pending command is no longer available.');
            return response('', 200);
        }

        // Temporarily bypass confirmation flag for this execution
        $result = $this->spawner->spawn($parsedCommand, $messageId, $chatId);
        $this->telegram->sendLongMessage($chatId, $result);

        return response('', 200);
    }

    private function handleDeny(int $chatId): Response
    {
        Cache::forget("fd_pending_command:{$chatId}");
        $this->telegram->sendMessage($chatId, 'Command cancelled.');
        return response('', 200);
    }

    /**
     * Handle an incoming photo message: download, analyze via vision skill, reply.
     */
    private function handlePhoto(int $chatId, ?int $messageId, array $photo, ?string $caption): void
    {
        try {
            Log::info('[Webhook/handlePhoto] Starting.', ['file_id' => $photo['file_id'], 'caption' => $caption]);
            $this->telegram->sendChatAction($chatId, 'typing');

            $localPath = $this->telegram->downloadTelegramFile($photo['file_id']);
            Log::info('[Webhook/handlePhoto] Downloaded.', ['local_path' => $localPath]);

            $question = $caption ?? 'Describe this image in detail.';

            $parsedCommand = $this->router->route('vision', [], $question);
            Log::info('[Webhook/handlePhoto] Routed.', ['found' => $parsedCommand !== null]);

            if ($parsedCommand === null) {
                $this->telegram->sendMessage($chatId, 'Image received, but the "vision" command is not available. Run the seeder or enable it from the dashboard.');
                return;
            }

            $enrichedInput = "Image received from Telegram. Local file path: {$localPath}\nUser question: {$question}";
            $enrichedCommand = new ParsedCommand(
                commandName:          $parsedCommand->commandName,
                arguments:            ['image_path' => $localPath, 'question' => $question],
                definition:           $parsedCommand->definition,
                requiresConfirmation: false,
                executionMode:        $parsedCommand->executionMode,
                naturalLanguageInput: $enrichedInput,
            );

            Log::info('[Webhook/handlePhoto] Spawning agent.', ['input' => $enrichedInput]);
            $result = $this->spawner->spawn($enrichedCommand, $messageId, $chatId);
            Log::info('[Webhook/handlePhoto] Agent result.', ['result_len' => strlen($result)]);
            $this->telegram->sendLongMessage($chatId, $result);
        } catch (\Throwable $e) {
            Log::error('[Webhook/handlePhoto] Exception.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->telegram->sendMessage($chatId, 'Sorry, I could not process the image.');
        }
    }

    /**
     * Handle an incoming voice message: download, transcribe directly via Whisper, then route.
     * Transcription is done inline (no agent spawn) to avoid webhook timeouts.
     */
    private function handleVoice(int $chatId, ?int $messageId, array $voice): void
    {
        try {
            Log::info('[Webhook/handleVoice] Starting.', ['file_id' => $voice['file_id']]);
            $this->telegram->sendChatAction($chatId, 'typing');

            // Step 1: Download the voice file from Telegram
            $localPath = $this->telegram->downloadTelegramFile($voice['file_id']);
            Log::info('[Webhook/handleVoice] Downloaded.', ['path' => $localPath]);

            // Step 2: Transcribe directly via Whisper (no agent overhead)
            $transcription = $this->transcribeWithWhisper($localPath);
            Log::info('[Webhook/handleVoice] Transcribed.', ['text' => mb_substr($transcription, 0, 100)]);

            // Step 3: Route the transcript as a normal text message
            $commandName = $this->interpretNaturalLanguage($transcription, $chatId) ?? 'chat';

            $parsedCommand = $this->router->route($commandName, [], $transcription);
            if ($parsedCommand === null) {
                Log::warning('[Webhook/handleVoice] No command found for transcript.');
                return;
            }

            // Show transcript only for action commands (not chat) — the chat agent
            // naturally acknowledges what was said, so a separate transcript is redundant.
            if ($commandName !== 'chat') {
                $this->telegram->sendMessage($chatId, "🎤 <i>Hai detto:</i> {$transcription}");
            }

            $result = $this->spawner->spawn($parsedCommand, $messageId, $chatId);
            $this->telegram->sendLongMessage($chatId, $result);
        } catch (\Throwable $e) {
            Log::error('[Webhook/handleVoice] Exception.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->telegram->sendMessage($chatId, 'Sorry, I could not process the voice message.');
        }
    }

    /**
     * Call OpenAI Whisper directly to transcribe a local audio file.
     * Returns the plain-text transcription.
     *
     * @throws \RuntimeException
     */
    private function transcribeWithWhisper(string $audioPath): string
    {
        $apiKey = env('OPENAI_API_KEY', '');
        if (empty($apiKey)) {
            throw new \RuntimeException('OPENAI_API_KEY is not configured.');
        }

        if (! file_exists($audioPath)) {
            throw new \RuntimeException("Audio file not found: {$audioPath}");
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
        ])->timeout(120)->attach(
            'file', file_get_contents($audioPath), basename($audioPath)
        )->post('https://api.openai.com/v1/audio/transcriptions', [
            'model'           => 'whisper-1',
            'response_format' => 'text',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Whisper API error: ' . $response->body());
        }

        return trim($response->body());
    }

    private function interpretNaturalLanguage(string $text, int $chatId): ?string
    {
        try {
            /** @var \Illuminate\Support\Collection $commands */
            $commands = \App\Models\AllowedCommand::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            // Build a detailed capability catalog so the LLM can make smart decisions
            $catalog = $this->buildNlCatalog($commands);

            $llm = app(\App\Services\Llm\LlmRouter::class);

            $systemPrompt = <<<'SYS'
You are the intent router for FlamingDragon, a personal AI agent.

Your ONLY job: read the user's message and return the name of the single command that best handles their request.

Rules:
- Reply with ONLY the command name — one word, no punctuation, no explanation.
- Use "chat" for greetings, general questions, calculations, or anything not covered by a specific command.
- Match intent, not exact words. "aggiungi latte" → shopping. "show my inbox" → gmail. "domani ho riunione alle 10" → calendar.
- If unsure between two commands, prefer the more specific one over "chat".
SYS;

            $userMsg = implode("\n\n", [
                "Available commands:\n{$catalog}",
                "User message: \"{$text}\"",
                "Command name:",
            ]);

            $response = $llm->chat(
                [['role' => 'user', 'content' => $userMsg]],
                [],
                ['system' => $systemPrompt],
            );

            $commandName = strtolower(trim($response->content));
            $commandName = preg_replace('/[^a-z0-9_]/', '', $commandName);

            // Validate against known commands
            if ($commands->pluck('name')->contains($commandName)) {
                return $commandName;
            }

            return 'chat';
        } catch (\Throwable $e) {
            Log::warning('[TelegramWebhookController] NL interpretation failed.', [
                'error' => $e->getMessage(),
            ]);
            return 'chat';
        }
    }

    /**
     * Build a rich, structured capability catalog for the NL classifier.
     *
     * @param  \Illuminate\Support\Collection  $commands
     */
    private function buildNlCatalog(\Illuminate\Support\Collection $commands): string
    {
        $skillMap = \App\Models\Skill::where('is_active', true)->get()->keyBy('name');
        $toolMap  = \App\Models\Tool::where('is_active', true)->get()->keyBy('name');

        $lines = [];

        foreach ($commands->groupBy('category') as $category => $group) {
            $lines[] = strtoupper($category) . ':';

            foreach ($group as $cmd) {
                $line = "  {$cmd->name}: {$cmd->description}";

                // Append skill-level detail
                if ($cmd->skill_required && isset($skillMap[$cmd->skill_required])) {
                    $sk = $skillMap[$cmd->skill_required];
                    $fm = $sk->parseFrontmatter();
                    // Add a short "can do" hint from the Overview line of SKILL.md
                    $overview = $this->extractSkillOverview($sk->readMarkdown() ?? '');
                    if ($overview) $line .= " — {$overview}";
                }

                // Append what the tools can do (brief, comma-separated display names)
                $toolLabels = [];
                foreach ($cmd->tools_allowed ?? [] as $t) {
                    if (isset($toolMap[$t])) {
                        $toolLabels[] = $toolMap[$t]->display_name;
                    }
                }
                if (! empty($toolLabels)) {
                    $line .= " [" . implode(', ', array_slice($toolLabels, 0, 4)) . "]";
                }

                $lines[] = $line;
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Extract the first meaningful sentence from a SKILL.md Overview section.
     */
    private function extractSkillOverview(string $markdown): string
    {
        if (preg_match('/##\s+Overview\s*\n+(.+?)(?:\n\n|\n#)/s', $markdown, $m)) {
            $text = trim(preg_replace('/\s+/', ' ', $m[1]));
            // Truncate to ~120 chars
            return mb_strlen($text) > 120 ? mb_substr($text, 0, 117) . '…' : $text;
        }
        return '';
    }
}
