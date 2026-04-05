<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'step_number',
        'action_type',
        'tool_name',
        'input_data',
        'output_data',
        'tokens_used',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'action_type' => ActionType::class,
        'created_at'  => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
