<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Parses raw Telegram update payloads into structured data.
 */
class TelegramParser
{
    /**
     * Extract the chat ID from a Telegram update payload.
     *
     * @param  array<string, mixed>  $update
     * @return int|null
     */
    public function extractChatId(array $update): ?int
    {
        return $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;
    }

    /**
     * Extract the message ID from a Telegram update payload.
     *
     * @param  array<string, mixed>  $update
     * @return int|null
     */
    public function extractMessageId(array $update): ?int
    {
        return $update['message']['message_id']
            ?? $update['callback_query']['message']['message_id']
            ?? null;
    }

    /**
     * Extract the raw text from a Telegram update payload.
     *
     * @param  array<string, mixed>  $update
     * @return string|null
     */
    public function extractText(array $update): ?string
    {
        return $update['message']['text']
            ?? $update['callback_query']['data']
            ?? null;
    }

    /**
     * Parse a raw message text into a command name and arguments array.
     *
     * Examples:
     *   "/deploy_site myapp"   → ['command' => 'deploy_site', 'args' => ['myapp']]
     *   "deploy myapp"         → ['command' => 'deploy', 'args' => ['myapp']]
     *   "just some text"       → ['command' => null, 'args' => [], 'natural_language' => 'just some text']
     *
     * @param  string  $text
     * @return array{command: string|null, args: array<string>, natural_language: string|null}
     */
    public function parseCommand(string $text): array
    {
        $text = trim($text);

        // Slash command: /commandname [args...]
        if (str_starts_with($text, '/')) {
            $parts   = preg_split('/\s+/', ltrim($text, '/'), 2);
            $command = strtolower($parts[0] ?? '');
            // Strip @BotName suffix if present (e.g., /help@MyBot)
            $command = explode('@', $command)[0];
            $argsRaw = $parts[1] ?? '';
            $args    = $argsRaw !== '' ? preg_split('/\s+/', $argsRaw) : [];

            return [
                'command'          => $command,
                'args'             => $args,
                'natural_language' => null,
            ];
        }

        // Natural-language message — no explicit command prefix
        return [
            'command'          => null,
            'args'             => [],
            'natural_language' => $text,
        ];
    }

    /**
     * Extract photo info from a Telegram update.
     * Returns the largest available photo (last in the array) with file_id, width, height.
     *
     * @param  array<string, mixed>  $update
     * @return array{file_id: string, width: int, height: int}|null
     */
    public function extractPhoto(array $update): ?array
    {
        $photos = $update['message']['photo'] ?? null;
        if (empty($photos) || ! is_array($photos)) {
            return null;
        }
        // Telegram sends photos in multiple sizes; last is the largest
        $largest = end($photos);
        return [
            'file_id' => $largest['file_id'],
            'width'   => $largest['width']  ?? 0,
            'height'  => $largest['height'] ?? 0,
        ];
    }

    /**
     * Extract voice message info from a Telegram update.
     * Returns file_id, duration, mime_type.
     *
     * @param  array<string, mixed>  $update
     * @return array{file_id: string, duration: int, mime_type: string}|null
     */
    public function extractVoice(array $update): ?array
    {
        $voice = $update['message']['voice'] ?? null;
        if (empty($voice) || ! is_array($voice)) {
            return null;
        }
        return [
            'file_id'   => $voice['file_id'],
            'duration'  => $voice['duration']  ?? 0,
            'mime_type' => $voice['mime_type'] ?? 'audio/ogg',
        ];
    }

    /**
     * Extract caption from a photo/video/audio message.
     *
     * @param  array<string, mixed>  $update
     * @return string|null
     */
    public function extractCaption(array $update): ?string
    {
        return $update['message']['caption'] ?? null;
    }

    /**
     * Determine whether a Telegram update is a message (not just an edited message, etc.).
     *
     * @param  array<string, mixed>  $update
     * @return bool
     */
    public function isMessage(array $update): bool
    {
        return isset($update['message']);
    }
}
