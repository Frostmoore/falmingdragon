<?php

declare(strict_types=1);

namespace App\Enums;

enum RiskLevel: string
{
    case Safe      = 'safe';
    case Moderate  = 'moderate';
    case Dangerous = 'dangerous';

    public function label(): string
    {
        return match($this) {
            self::Safe      => 'Safe',
            self::Moderate  => 'Moderate',
            self::Dangerous => 'Dangerous',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::Safe      => 'green',
            self::Moderate  => 'yellow',
            self::Dangerous => 'red',
        };
    }
}
