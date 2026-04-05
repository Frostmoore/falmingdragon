<?php

declare(strict_types=1);

namespace App\Enums;

enum AgentStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Timeout = 'timeout';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Queued    => 'Queued',
            self::Running   => 'Running',
            self::Completed => 'Completed',
            self::Failed    => 'Failed',
            self::Timeout   => 'Timed Out',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Queued    => 'yellow',
            self::Running   => 'blue',
            self::Completed => 'green',
            self::Failed    => 'red',
            self::Timeout   => 'orange',
            self::Cancelled => 'gray',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Timeout, self::Cancelled]);
    }
}
