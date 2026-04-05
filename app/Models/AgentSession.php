<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgentStatus;
use App\Enums\ExecutionMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSession extends Model
{
    protected $fillable = [
        'session_uuid',
        'telegram_message_id',
        'command',
        'raw_input',
        'status',
        'execution_mode',
        'agent_pid',
        'queue_job_id',
        'tools_granted',
        'llm_provider',
        'llm_model',
        'tokens_input',
        'tokens_output',
        'result_summary',
        'result_full',
        'error_message',
        'started_at',
        'completed_at',
        'timeout_seconds',
    ];

    protected $casts = [
        'status'         => AgentStatus::class,
        'execution_mode' => ExecutionMode::class,
        'tools_granted'  => 'array',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class, 'session_id');
    }

    /** Total tokens consumed by this session. */
    public function totalTokens(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }

    /** Duration in seconds (null if not yet completed). */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->completed_at === null) {
            return null;
        }
        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }

    public function isRunning(): bool
    {
        return $this->status === AgentStatus::Running;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
