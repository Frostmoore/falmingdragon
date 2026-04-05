<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\AllowedCommand;
use Illuminate\Support\Facades\Log;

/**
 * Enforces the allow-list: only explicitly registered and active commands may execute.
 */
class AllowListGuard
{
    /**
     * Check whether a command name is allowed to execute.
     *
     * @param  string  $commandName
     * @return bool
     */
    public function isAllowed(string $commandName): bool
    {
        if (! config('flamingdragon.security.allow_list_strict', true)) {
            return true;
        }

        $command = AllowedCommand::where('name', $commandName)
            ->where('is_active', true)
            ->first();

        if ($command === null) {
            Log::warning('[AllowListGuard] Blocked command not in allow-list.', [
                'command' => $commandName,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Retrieve the AllowedCommand model if it is active, or null.
     *
     * @param  string  $commandName
     * @return AllowedCommand|null
     */
    public function getCommand(string $commandName): ?AllowedCommand
    {
        return AllowedCommand::where('name', $commandName)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check that all requested tools are in the command's allowed tool list.
     *
     * @param  array<string>  $requestedTools
     * @param  array<string>  $allowedTools
     * @return bool
     */
    public function toolsAreGranted(array $requestedTools, array $allowedTools): bool
    {
        foreach ($requestedTools as $tool) {
            if (! in_array($tool, $allowedTools, true)) {
                Log::warning('[AllowListGuard] Tool not granted for this command.', [
                    'tool'          => $tool,
                    'allowed_tools' => $allowedTools,
                ]);
                return false;
            }
        }
        return true;
    }
}
