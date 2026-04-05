<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LlmProvider;
use App\Services\Telegram\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class WizardController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegram,
    ) {}

    /** GET /wizard */
    public function index(): View
    {
        return view('dashboard.wizard', [
            'appUrl'          => config('app.url'),
            'webhookUrl'      => url('/api/telegram/webhook'),
            'botToken'        => config('flamingdragon.telegram.bot_token', ''),
            'webhookSecret'   => config('flamingdragon.telegram.webhook_secret', ''),
            'allowedChatIds'  => implode(',', config('flamingdragon.telegram.allowed_chat_ids', [])),
            'defaultProvider' => config('flamingdragon.llm.default_provider', 'anthropic'),
            'defaultModel'    => config('flamingdragon.llm.default_model', 'claude-sonnet-4-20250514'),
            'anthropicKey'    => env('ANTHROPIC_API_KEY', '') !== '' ? '(configurato)' : '',
            'openaiKey'       => env('OPENAI_API_KEY', '') !== '' ? '(configurato)' : '',
            'queueDriver'     => config('queue.default', 'database'),
            'providers'       => LlmProvider::orderBy('name')->get(),
        ]);
    }

    /**
     * POST /wizard/save-env
     * Writes a set of key=value pairs to .env
     */
    public function saveEnv(Request $request): JsonResponse
    {
        $allowed = [
            'FD_TELEGRAM_BOT_TOKEN',
            'FD_TELEGRAM_WEBHOOK_SECRET',
            'FD_TELEGRAM_ALLOWED_CHAT_IDS',
            'ANTHROPIC_API_KEY',
            'OPENAI_API_KEY',
            'FD_OLLAMA_BASE_URL',
            'FD_LLM_DEFAULT_PROVIDER',
            'FD_LLM_DEFAULT_MODEL',
        ];

        $data = $request->validate([
            'values' => 'required|array',
        ]);

        $pairs = [];
        foreach ($data['values'] as $key => $value) {
            if (! in_array($key, $allowed, true)) {
                continue;
            }
            $pairs[$key] = $value;
        }

        if (empty($pairs)) {
            return response()->json(['success' => false, 'message' => 'No valid keys to save.'], 422);
        }

        try {
            $this->writeEnvValues($pairs);
            // Reload the config cache
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            return response()->json(['success' => true, 'saved' => array_keys($pairs)]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /wizard/test-telegram
     * Verifies the bot token is valid by calling getMe.
     */
    public function testTelegram(Request $request): JsonResponse
    {
        $token = $request->input('token', config('flamingdragon.telegram.bot_token', ''));

        if (empty($token)) {
            return response()->json(['success' => false, 'message' => 'Token non impostato.']);
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
            $body     = $response->json();

            if ($body['ok'] ?? false) {
                return response()->json([
                    'success'  => true,
                    'bot_name' => $body['result']['first_name'] ?? 'Unknown',
                    'username' => $body['result']['username'] ?? 'Unknown',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $body['description'] ?? 'Risposta non valida da Telegram.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /wizard/register-webhook
     * Calls setWebhook on the Telegram API.
     */
    public function registerWebhook(Request $request): JsonResponse
    {
        $token   = $request->input('token', config('flamingdragon.telegram.bot_token', ''));
        $url     = $request->input('url', url('/api/telegram/webhook'));
        $secret  = $request->input('secret', config('flamingdragon.telegram.webhook_secret', ''));

        if (empty($token)) {
            return response()->json(['success' => false, 'message' => 'Token bot non impostato.']);
        }

        try {
            $payload = ['url' => $url];
            if (! empty($secret)) {
                $payload['secret_token'] = $secret;
            }

            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$token}/setWebhook",
                $payload
            );
            $body = $response->json();

            if ($body['ok'] ?? false) {
                return response()->json(['success' => true, 'description' => $body['description'] ?? 'Webhook registrato.']);
            }

            return response()->json(['success' => false, 'message' => $body['description'] ?? 'Errore sconosciuto.']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /wizard/test-llm
     * Sends a tiny test message to the selected LLM provider.
     */
    public function testLlm(Request $request): JsonResponse
    {
        $providerName = $request->input('provider', 'anthropic');

        try {
            $router   = app(\App\Services\Llm\LlmRouter::class);
            $provider = $router->getProvider($providerName);
            $response = $provider->chat([
                ['role' => 'user', 'content' => 'Reply with only the word: OK'],
            ]);

            return response()->json([
                'success'  => true,
                'response' => trim($response->content),
                'tokens'   => $response->totalTokens(),
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * POST /wizard/send-test-message
     * Sends a test Telegram message.
     * Accepts token and chat_id directly from the wizard form to bypass stale config cache.
     */
    public function sendTestMessage(Request $request): JsonResponse
    {
        // Prefer values from the request (wizard form) — config() may be stale
        // if .env was just written during the same PHP session.
        $token  = $request->input('token')   ?: config('flamingdragon.telegram.bot_token', '')  ?: env('FD_TELEGRAM_BOT_TOKEN', '');
        $chatId = $request->input('chat_id') ?: (config('flamingdragon.telegram.allowed_chat_ids', [null])[0] ?? null)
                                              ?: (array_filter(explode(',', (string) env('FD_TELEGRAM_ALLOWED_CHAT_IDS', '')))[0] ?? null);

        if (empty($token)) {
            return response()->json(['success' => false, 'message' => 'Token bot non trovato. Torna allo step 1 e salva il token.']);
        }

        if (empty($chatId)) {
            return response()->json(['success' => false, 'message' => 'Chat ID non trovato. Torna allo step 2 e salva il Chat ID.']);
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id'    => (int) $chatId,
                    'text'       => "🔥 <b>FlamingDragon è attivo!</b>\n\nConfigurazione completata con successo.\nUsa /help per vedere i comandi disponibili.",
                    'parse_mode' => 'HTML',
                ]
            );

            $body = $response->json();

            if ($body['ok'] ?? false) {
                return response()->json(['success' => true, 'chat_id' => (int) $chatId, 'message' => 'Messaggio inviato!']);
            }

            return response()->json([
                'success' => false,
                'message' => $body['description'] ?? 'Risposta non valida da Telegram.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Write key=value pairs to the .env file safely (line-by-line, no regex).
     *
     * @param  array<string, string>  $pairs
     * @throws \RuntimeException
     */
    private function writeEnvValues(array $pairs): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            throw new \RuntimeException('.env file non trovato in: ' . $envPath);
        }

        if (! is_writable($envPath)) {
            throw new \RuntimeException(
                'Il file .env non è scrivibile. ' .
                'Su Windows controlla che il file non sia in sola lettura (tasto destro → Proprietà). ' .
                'Path: ' . $envPath
            );
        }

        // Read line by line — avoids any regex replacement-string pitfalls.
        // Use rtrim on each line to handle both LF and CRLF endings on Windows.
        $rawLines = file($envPath, FILE_IGNORE_NEW_LINES) ?: [];
        $lines    = array_map(static fn (string $l) => rtrim($l, "\r"), $rawLines);
        $written  = array_fill_keys(array_keys($pairs), false);
        $result   = [];

        foreach ($lines as $line) {
            $replaced = false;
            foreach ($pairs as $key => $value) {
                // Match "KEY=" at the start of the line
                if (str_starts_with($line, $key . '=')) {
                    $result[]       = $key . '=' . $this->escapeEnvValue($value);
                    $written[$key]  = true;
                    $replaced       = true;
                    break;
                }
            }
            if (! $replaced) {
                $result[] = $line;
            }
        }

        // Append any keys that were not already in the file
        foreach ($written as $key => $found) {
            if (! $found) {
                $result[] = $key . '=' . $this->escapeEnvValue($pairs[$key]);
            }
        }

        $bytes = file_put_contents($envPath, implode("\n", $result) . "\n", LOCK_EX);

        if ($bytes === false) {
            throw new \RuntimeException(
                'Scrittura del file .env fallita. ' .
                'Verifica i permessi di scrittura su: ' . $envPath
            );
        }
    }

    /**
     * Wrap the value in double quotes if it contains whitespace or shell-special characters.
     */
    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        // Quote if the value contains spaces, quotes, #, $ or backslashes
        if (preg_match('/[\s"\'#$\\\\]/', $value)) {
            return '"' . str_replace(['"', '\\'], ['\\"', '\\\\'], $value) . '"';
        }
        return $value;
    }
}
