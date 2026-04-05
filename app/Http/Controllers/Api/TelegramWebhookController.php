<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agent\AgentSpawner;
use App\Services\Command\CommandRouter;
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
        private readonly TelegramParser  $parser,
        private readonly TelegramService $telegram,
        private readonly CommandRouter   $router,
        private readonly AgentSpawner    $spawner,
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
            $text      = $this->parser->extractText($update);

            if ($chatId === null || $text === null) {
                return response('', 200);
            }

            $parsed = $this->parser->parseCommand($text);

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

    private function interpretNaturalLanguage(string $text, int $chatId): ?string
    {
        try {
            $commands = \App\Models\AllowedCommand::where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'description']);

            $commandList = $commands
                ->map(fn ($c) => "- {$c->name}: {$c->description}")
                ->implode("\n");

            $llm      = app(\App\Services\Llm\LlmRouter::class);
            $response = $llm->chat([
                [
                    'role'    => 'user',
                    'content' => implode("\n", [
                        'You are a command classifier. Given a user message and a list of available commands, return ONLY the command name that best matches the user intent.',
                        'If the message is general conversation, a question, or does not map to any specific command, return "chat".',
                        'Reply with a single word — the command name. No explanation, no punctuation.',
                        '',
                        'Available commands:',
                        $commandList,
                        '',
                        "User message: \"{$text}\"",
                    ]),
                ],
            ]);

            $commandName = strtolower(trim($response->content));
            // Strip any accidental punctuation the LLM might add
            $commandName = preg_replace('/[^a-z0-9_]/', '', $commandName);

            // Validate: must be a known command name
            if ($commands->pluck('name')->contains($commandName)) {
                return $commandName;
            }

            // Unknown or 'chat' → fall back to chat
            return 'chat';
        } catch (\Throwable $e) {
            Log::warning('[TelegramWebhookController] NL interpretation failed.', [
                'error' => $e->getMessage(),
            ]);
            return 'chat';
        }
    }
}
