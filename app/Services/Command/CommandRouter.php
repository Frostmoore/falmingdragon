<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Enums\ExecutionMode;
use App\Models\AllowedCommand;
use App\Services\Security\AllowListGuard;
use Illuminate\Support\Facades\Log;

/**
 * Matches raw Telegram input to allowed commands and builds ParsedCommand DTOs.
 */
class CommandRouter
{
    public function __construct(
        private readonly AllowListGuard $guard,
    ) {}

    /**
     * Route a parsed command name + args to an AllowedCommand record.
     *
     * @param  string        $commandName
     * @param  array<string> $arguments
     * @param  string|null   $naturalLanguage  Original NL input (for logging/context).
     * @return ParsedCommand|null  Null means command not found or not allowed.
     */
    public function route(
        string $commandName,
        array $arguments = [],
        ?string $naturalLanguage = null,
    ): ?ParsedCommand {
        $definition = $this->guard->getCommand($commandName);

        if ($definition === null) {
            Log::info('[CommandRouter] Command not found in allow-list.', [
                'command' => $commandName,
            ]);
            return null;
        }

        $executionMode = $this->resolveExecutionMode($definition);

        return new ParsedCommand(
            commandName:          $definition->name,
            arguments:            $arguments,
            definition:           $definition,
            requiresConfirmation: $definition->is_dangerous
                && ! $definition->skip_confirmation
                && config('flamingdragon.security.dangerous_commands_require_confirmation', true),
            executionMode:        $executionMode,
            naturalLanguageInput: $naturalLanguage,
        );
    }

    /**
     * Resolve the effective execution mode.
     * "auto" mode uses the timeout threshold defined in config to decide sync vs async.
     */
    private function resolveExecutionMode(AllowedCommand $command): ExecutionMode
    {
        if ($command->execution_mode !== ExecutionMode::Auto) {
            return $command->execution_mode;
        }

        $threshold = config('flamingdragon.execution.sync_threshold', 30);

        return $command->timeout_seconds <= $threshold
            ? ExecutionMode::Sync
            : ExecutionMode::Async;
    }

    /**
     * Return all active allowed commands, optionally filtered by category.
     *
     * @param  string|null  $category
     * @return \Illuminate\Database\Eloquent\Collection<AllowedCommand>
     */
    public function listCommands(?string $category = null)
    {
        $query = AllowedCommand::where('is_active', true);

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->orderBy('category')->orderBy('name')->get();
    }
}
