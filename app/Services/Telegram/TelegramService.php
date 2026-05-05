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

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    /**
     * Send a "typing…" / "upload_photo" / etc. action indicator.
     */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        try {
            Http::timeout(5)->post("{$this->apiBase}/sendChatAction", [
                'chat_id' => $chatId,
                'action'  => $action,
            ]);
        } catch (Throwable) {
            // non-critical
        }
    }

    /**
     * Download a file from Telegram by file_id.
     * Returns the local absolute path where the file was saved.
     *
     * @throws \RuntimeException
     */
    public function downloadTelegramFile(string $fileId): string
    {
        // Step 1: get the file path on Telegram's servers
        $infoResp = Http::timeout(10)->get("{$this->apiBase}/getFile", ['file_id' => $fileId]);
        $filePath = $infoResp->json('result.file_path');

        if (! $filePath) {
            throw new \RuntimeException("TelegramService: could not get file path for file_id {$fileId}");
        }

        // Step 2: download the actual bytes
        $token    = config('flamingdragon.telegram.bot_token', '');
        $fileUrl  = "https://api.telegram.org/file/bot{$token}/{$filePath}";
        $content  = Http::timeout(30)->get($fileUrl)->body();

        // Step 3: save to storage/app/public/media/
        $dir = storage_path('app/public/media');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext       = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'bin';
        $filename  = 'tg_' . substr($fileId, -12) . '_' . time() . '.' . $ext;
        $localPath = "{$dir}/{$filename}";
        file_put_contents($localPath, $content);

        return $localPath;
    }

    /**
     * Get the public URL for a locally stored media file.
     */
    public function publicMediaUrl(string $localPath): string
    {
        $filename = basename($localPath);
        return url("storage/media/{$filename}");
    }

    /**
     * Send a photo to a chat.
     * $photo can be a public URL, a Telegram file_id, or a local file path.
     */
    public function sendPhoto(int|string $chatId, string $photo, string $caption = ''): bool
    {
        try {
            $params = ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML'];

            // Local file → multipart
            if (file_exists($photo)) {
                $response = Http::timeout(30)
                    ->attach('photo', file_get_contents($photo), basename($photo))
                    ->post("{$this->apiBase}/sendPhoto", $params);
            } else {
                // URL or file_id
                $response = Http::timeout(30)->post("{$this->apiBase}/sendPhoto", array_merge($params, ['photo' => $photo]));
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::error('[TelegramService] sendPhoto exception.', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a voice message (OGG/OPUS) to a chat.
     */
    public function sendVoice(int|string $chatId, string $audioPath, string $caption = ''): bool
    {
        try {
            $response = Http::timeout(60)
                ->attach('voice', file_get_contents($audioPath), basename($audioPath))
                ->post("{$this->apiBase}/sendVoice", [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                ]);
            return $response->successful();
        } catch (Throwable $e) {
            Log::error('[TelegramService] sendVoice exception.', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send an audio file (MP3) to a chat.
     */
    public function sendAudio(int|string $chatId, string $audioPath, string $title = '', string $caption = ''): bool
    {
        try {
            $response = Http::timeout(60)
                ->attach('audio', file_get_contents($audioPath), basename($audioPath))
                ->post("{$this->apiBase}/sendAudio", [
                    'chat_id' => $chatId,
                    'title'   => $title,
                    'caption' => $caption,
                ]);
            return $response->successful();
        } catch (Throwable $e) {
            Log::error('[TelegramService] sendAudio exception.', ['exception' => $e->getMessage()]);
            return false;
        }
    }
}
