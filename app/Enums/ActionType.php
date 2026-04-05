<?php

declare(strict_types=1);

namespace App\Enums;

enum ActionType: string
{
    case LlmCall     = 'llm_call';
    case ToolUse     = 'tool_use';
    case SkillInvoke = 'skill_invoke';
    case Decision    = 'decision';
    case Error       = 'error';
    case UserPrompt  = 'user_prompt';

    public function label(): string
    {
        return match($this) {
            self::LlmCall     => 'Large Language Model Call',
            self::ToolUse     => 'Tool Use',
            self::SkillInvoke => 'Skill Invocation',
            self::Decision    => 'Decision',
            self::Error       => 'Error',
            self::UserPrompt  => 'User Prompt',
        };
    }
}
