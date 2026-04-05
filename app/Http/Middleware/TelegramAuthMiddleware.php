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
        // 1. Validate the webhook secret
        $expectedSecret = config('flamingdragon.telegram.webhook_secret', '');

        if (! empty($expectedSecret)) {
            $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

            if (! hash_equals($expectedSecret, $receivedSecret)) {
                // Silent 200 — do not reveal validation failure to potential attackers
                return response('', 200);
            }
        }

        // 2. Extract and validate the chat ID
        $update = $request->json()->all();
        $chatId = $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;

        if ($chatId === null) {
            // Not a message update — ignore silently
            return response('', 200);
        }

        $allowedIds = config('flamingdragon.telegram.allowed_chat_ids', []);

        if (! in_array((int) $chatId, $allowedIds, true)) {
            // SECURITY: Do NOT log the chat ID — prevents information leakage through logs
            return response('', 200);
        }

        return $next($request);
    }
}
