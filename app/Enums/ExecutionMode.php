<?php

declare(strict_types=1);

namespace App\Enums;

enum ExecutionMode: string
{
    case Sync  = 'sync';
    case Async = 'async';
    case Auto  = 'auto';

    public function label(): string
    {
        return match($this) {
            self::Sync  => 'Synchronous',
            self::Async => 'Asynchronous',
            self::Auto  => 'Automatic',
        };
    }
}
