<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\RiskCategory;
use App\Models\AllowedCommand;
use App\Services\Command\ParsedCommand;
use Illuminate\Support\Facades\Cache;

/**
 * Reusable component for the Telegram confirm/deny flow on dangerous commands.
 *
 * Responsibilities:
 *   - Decide whether a command needs a confirmation gate.
 *   - Store and retrieve the pending command for a chat ID.
 *   - Build the user-facing warning prompt shown before /confirm or /deny.
 *
 * Previously this logic was embedded in CommandRouter + TelegramWebhookController.
 */
class ConfirmationGate
{
    private const CACHE_KEY    = 'fd_pending_command:%d';
    private const CACHE_TTL    = 5 * 60; // seconds

    /**
     * True if the command must go through the confirm/deny flow.
     */
    public function requiresGate(AllowedCommand $command): bool
    {
        if (! $command->is_dangerous) {
            return false;
        }
        if ($command->skip_confirmation) {
            return false;
        }
        return (bool) config('flamingdragon.security.dangerous_commands_require_confirmation', true);
    }

    /**
     * Store a pending ParsedCommand for the given chat ID.
     * Overwrites any previous pending command for the same chat.
     */
    public function store(int $chatId, ParsedCommand $cmd): void
    {
        Cache::put(
            $this->cacheKey($chatId),
            ['command' => $cmd->commandName, 'args' => $cmd->arguments],
            self::CACHE_TTL
        );
    }

    /**
     * Retrieve the stored pending command payload for a chat ID.
     * Returns null if nothing is pending or the entry has expired.
     *
     * @return array{command: string, args: array<string, mixed>}|null
     */
    public function retrieve(int $chatId): ?array
    {
        return Cache::get($this->cacheKey($chatId));
    }

    /**
     * Clear the pending command for a chat ID (after confirm or deny).
     */
    public function clear(int $chatId): void
    {
        Cache::forget($this->cacheKey($chatId));
    }

    /**
     * Build the Telegram HTML warning message shown to the user before confirmation.
     */
    public function buildPrompt(AllowedCommand $command): string
    {
        $name     = htmlspecialchars($command->name, ENT_XML1);
        $category = $this->riskCategoryLabel($command);

        $header = "⚠️ <b>Comando pericoloso:</b> <code>{$name}</code>";

        if ($category !== null) {
            $header .= "\n<i>Categoria di rischio: {$category}</i>";
        }

        return $header . "\n\nInvia /confirm per eseguire o /deny per annullare.";
    }

    // -------------------------------------------------------------------------

    private function cacheKey(int $chatId): string
    {
        return sprintf(self::CACHE_KEY, $chatId);
    }

    private function riskCategoryLabel(AllowedCommand $command): ?string
    {
        // risk_category is on the Tool, not the command; resolve via tools_allowed
        $toolNames = $command->tools_allowed ?? [];
        if (empty($toolNames)) {
            return null;
        }

        $tool = \App\Models\Tool::whereIn('name', $toolNames)
            ->whereNotNull('risk_category')
            ->first();

        if ($tool === null || $tool->risk_category === null) {
            return null;
        }

        $category = $tool->risk_category instanceof RiskCategory
            ? $tool->risk_category
            : RiskCategory::tryFrom((string) $tool->risk_category);

        return $category?->label();
    }
}
