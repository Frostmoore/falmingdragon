<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles communication with the Telegram Bot API.
 */
class TelegramService
{
    private string $apiBase;

    public function __construct()
    {
        $token = config('flamingdragon.telegram.bot_token', '');
        $this->apiBase = "https://api.telegram.org/bot{$token}";
    }

    /**
     * Send a text message to a chat.
     *
     * @param  int|string  $chatId
     * @param  string      $text
     * @param  string      $parseMode  'HTML' | 'Markdown' | 'MarkdownV2'
     * @return bool
     */
    public function sendMessage(int|string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        try {
            $response = Http::timeout(10)->post("{$this->apiBase}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => $parseMode,
            ]);

            if (! $response->successful()) {
                Log::error('[TelegramService] sendMessage failed.', [
                    'chat_id'  => $chatId,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('[TelegramService] sendMessage exception.', [
                'chat_id'   => $chatId,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a long message, splitting it into chunks if needed (Telegram limit: 4096 chars).
     *
     * @param  int|string  $chatId
     * @param  string      $text
     */
    public function sendLongMessage(int|string $chatId, string $text): void
    {
        $chunks = str_split($text, 4000);
        foreach ($chunks as $chunk) {
            $this->sendMessage($chatId, $chunk);
        }
    }

    /**
     * Set the webhook URL for this bot.
     *
     * @param  string  $url
     * @param  string  $secret
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, string $secret = ''): array
    {
        try {
            $payload = ['url' => $url];
            if ($secret !== '') {
                $payload['secret_token'] = $secret;
            }

            $response = Http::timeout(10)->post("{$this->apiBase}/setWebhook", $payload);
            return $response->json() ?? [];
        } catch (Throwable $e) {
            Log::error('[TelegramService] setWebhook exception.', ['exception' => $e->getMessage()]);
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Get current webhook info.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiBase}/getWebhookInfo");
            return $response->json() ?? [];
        } catch (Throwable $e) {
            Log::error('[TelegramService] getWebhookInfo exception.', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get basic bot information.
     *
     * @return array<string, mixed>
     */
    public function getMe(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiBase}/getMe");
            return $response->json() ?? [];
        } catch (Throwable $e) {
            Log::error('[TelegramService] getMe exception.', ['exception' => $e->getMessage()]);
            return [];
        }
    }
}
