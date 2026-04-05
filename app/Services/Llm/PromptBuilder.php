<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Models\AllowedCommand;
use App\Models\Memory;
use App\Models\Skill;
use Illuminate\Support\Facades\Log;

/**
 * Assembles the system prompt and context for agent sessions.
 */
class PromptBuilder
{
    /**
     * Build the full system prompt for an agent session.
     *
     * @param  AllowedCommand        $command
     * @param  array<string>         $grantedTools
     * @param  array<Memory>         $memories
     * @param  Skill|null            $skill
     * @param  int                   $maxToolCalls
     * @param  int                   $timeoutSeconds
     * @return string
     */
    public function build(
        AllowedCommand $command,
        array $grantedTools,
        array $memories,
        ?Skill $skill,
        int $maxToolCalls,
        int $timeoutSeconds,
    ): string {
        $base       = $this->loadBasePrompt();
        $memoryCtx  = $this->buildMemoryContext($memories);
        $skillCtx   = $this->buildSkillContext($skill);
        $toolCtx    = $this->buildToolContext($grantedTools);
        $constraints = $this->buildConstraints($command, $maxToolCalls, $timeoutSeconds);

        return implode("\n\n", array_filter([
            $base,
            $memoryCtx,
            $skillCtx,
            $toolCtx,
            $constraints,
        ]));
    }

    /**
     * Load the base system prompt from file.
     */
    private function loadBasePrompt(): string
    {
        $path = config('flamingdragon.llm.system_prompt_path');

        if ($path && file_exists($path)) {
            return (string) file_get_contents($path);
        }

        Log::warning('[PromptBuilder] System prompt file not found, using inline fallback.', [
            'path' => $path,
        ]);

        return <<<'PROMPT'
You are FlamingDragon, a personal AI agent running on the user's server.
You execute commands precisely, use only the tools granted to you, and always report results clearly.
You are NOT a chatbot. You are an execution engine with intelligence.
Never attempt to access resources outside your granted tools and paths.
Always respond in the same language the user used.
PROMPT;
    }

    /**
     * Format relevant memories as context block.
     *
     * @param  array<Memory>  $memories
     */
    private function buildMemoryContext(array $memories): string
    {
        if (empty($memories)) {
            return '';
        }

        $lines = ["## Relevant Memories\n"];
        foreach ($memories as $memory) {
            $lines[] = "- [{$memory->namespace}/{$memory->key}]: {$memory->value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format the skill instructions block.
     */
    private function buildSkillContext(?Skill $skill): string
    {
        if ($skill === null) {
            return '';
        }

        $md = $skill->readMarkdown();
        if ($md === null) {
            return '';
        }

        return "## Active Skill: {$skill->display_name}\n\n{$md}";
    }

    /**
     * List the tools available in this session.
     *
     * @param  array<string>  $tools
     */
    private function buildToolContext(array $tools): string
    {
        if (empty($tools)) {
            return "## Available Tools\nNo tools are available for this session.";
        }

        return "## Available Tools\nYou may use the following tools: " . implode(', ', $tools) . '.';
    }

    /**
     * Append session constraint metadata.
     */
    private function buildConstraints(AllowedCommand $command, int $maxToolCalls, int $timeoutSeconds): string
    {
        return <<<CONSTRAINTS
## Session Constraints
- Command: {$command->name}
- Maximum tool calls allowed: {$maxToolCalls}
- Session timeout: {$timeoutSeconds} seconds
- If you exceed these limits, the session will be terminated automatically.
CONSTRAINTS;
    }
}
