<?php

declare(strict_types=1);

namespace App\Enums;

enum ToolType: string
{
    case Builtin   = 'builtin';
    case Script    = 'script';
    case Api       = 'api';
    case Composite = 'composite';

    public function label(): string
    {
        return match($this) {
            self::Builtin   => 'Built-in',
            self::Script    => 'Script',
            self::Api       => 'API',
            self::Composite => 'Composite',
        };
    }
}
