<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExecutionMode;
use Illuminate\Database\Eloquent\Model;

class AllowedCommand extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'execution_mode',
        'timeout_seconds',
        'tools_allowed',
        'llm_provider_override',
        'llm_model_override',
        'skill_required',
        'system_prompt',
        'is_dangerous',
        'skip_confirmation',
        'is_active',
    ];

    protected $casts = [
        'execution_mode'    => ExecutionMode::class,
        'tools_allowed'     => 'array',
        'is_dangerous'      => 'boolean',
        'skip_confirmation' => 'boolean',
        'is_active'         => 'boolean',
    ];

    /** Resolve the effective execution mode (auto → sync by default unless overridden by orchestrator). */
    public function resolvedExecutionMode(): ExecutionMode
    {
        return $this->execution_mode === ExecutionMode::Auto
            ? ExecutionMode::Sync
            : $this->execution_mode;
    }
}
