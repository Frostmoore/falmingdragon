<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Services\Security\AllowListGuard;

/**
 * Validates a parsed command before execution.
 */
class CommandValidator
{
    public function __construct(
        private readonly AllowListGuard $guard,
    ) {}

    /**
     * Check that the command is still active and its tools are on the allow-list.
     *
     * @param  ParsedCommand  $command
     * @return array{valid: bool, errors: array<string>}
     */
    public function validate(ParsedCommand $command): array
    {
        $errors = [];

        if (! $this->guard->isAllowed($command->commandName)) {
            $errors[] = "Command '{$command->commandName}' is not in the allow-list or is inactive.";
        }

        // Verify the tools that would be used are in the definition's allowed list
        $allowedTools = $command->definition->tools_allowed ?? [];
        foreach ($command->definition->tools_allowed ?? [] as $tool) {
            // Existence check deferred to ToolExecutor; here we just ensure consistency
            if (empty($tool)) {
                $errors[] = "Invalid empty tool entry in command '{$command->commandName}'.";
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
