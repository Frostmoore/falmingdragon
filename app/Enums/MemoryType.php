<?php

declare(strict_types=1);

namespace App\Enums;

enum MemoryType: string
{
    case Fact        = 'fact';
    case Preference  = 'preference';
    case Context     = 'context';
    case Instruction = 'instruction';

    public function label(): string
    {
        return match($this) {
            self::Fact        => 'Fact',
            self::Preference  => 'Preference',
            self::Context     => 'Context',
            self::Instruction => 'Instruction',
        };
    }
}
