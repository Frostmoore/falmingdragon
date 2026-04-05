<?php

declare(strict_types=1);

namespace App\Services\Command;

use App\Enums\ExecutionMode;
use App\Models\AllowedCommand;

/**
 * Data Transfer Object produced by the command parser.
 */
final class ParsedCommand
{
    public function __construct(
        public readonly string         $commandName,
        public readonly array          $arguments,
        public readonly AllowedCommand $definition,
        public readonly bool           $requiresConfirmation,
        public readonly ExecutionMode  $executionMode,
        public readonly ?string        $naturalLanguageInput = null,
    ) {}
}
