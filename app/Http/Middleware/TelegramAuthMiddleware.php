<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Telegram webhook requests:
 *  1. Checks the webhook secret token header.
 *  2. Confirms the chat_id is in the allow-list.
 *
 * On failure: returns HTTP 200 with an empty body to prevent information leakage.
 */
class TelegramAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('[TelegramAuth] Webhook request received.', [
            'ip'      => $request->ip(),
            'headers' => $request->headers->keys(),
        ]);

        // 1. Validate the webhook secret
        $expectedSecret = config('flamingdragon.telegram.webhook_secret', '');

        if (! empty($expectedSecret)) {
            $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

            if (! hash_equals($expectedSecret, $receivedSecret)) {
                Log::warning('[TelegramAuth] Secret token mismatch — request blocked.');
                return response('', 200);
            }
        }

        // 2. Extract and validate the chat ID
        $update = $request->json()->all();
        $chatId = $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;

        Log::info('[TelegramAuth] Parsed update.', [
            'chat_id'      => $chatId,
            'update_keys'  => array_keys($update),
            'message_keys' => array_keys($update['message'] ?? []),
        ]);

        if ($chatId === null) {
            Log::warning('[TelegramAuth] No chat_id found — ignored.');
            return response('', 200);
        }

        $allowedIds = config('flamingdragon.telegram.allowed_chat_ids', []);

        if (! in_array((int) $chatId, $allowedIds, true)) {
            Log::warning('[TelegramAuth] Chat ID not in allow-list — blocked.', [
                'chat_id'     => $chatId,
                'allowed_ids' => $allowedIds,
            ]);
            return response('', 200);
        }

        Log::info('[TelegramAuth] Request authorized, passing to controller.');
        return $next($request);
    }
}
