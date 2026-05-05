<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Models\AllowedCommand;
use App\Models\Memory;
use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Support\Facades\Log;

/**
 * Assembles the system prompt and context for agent sessions.
 */
class PromptBuilder
{
    /**
     * Build the full system prompt for an agent session.
     *
     * @param  AllowedCommand              $command
     * @param  array<string>               $grantedTools
     * @param  array<Memory>               $memories
     * @param  Skill|null                  $skill
     * @param  int                         $maxToolCalls
     * @param  int                         $timeoutSeconds
     * @param  array<int, array>           $toolDefinitions  Full schemas from AgentOrchestrator
     */
    public function build(
        AllowedCommand $command,
        array $grantedTools,
        array $memories,
        ?Skill $skill,
        int $maxToolCalls,
        int $timeoutSeconds,
        array $toolDefinitions = [],
    ): string {
        $base        = $this->loadBasePrompt();
        $commandCtx  = ! empty($command->system_prompt)
            ? "## Command-specific Instructions\n\n" . trim($command->system_prompt)
            : '';
        $memoryCtx   = $this->buildMemoryContext($memories);
        $skillCtx    = $this->buildSkillContext($skill);
        $toolCtx     = $this->buildToolContext($grantedTools, $toolDefinitions);
        $constraints = $this->buildConstraints($command, $maxToolCalls, $timeoutSeconds);

        return implode("\n\n", array_filter([
            $base,
            $commandCtx,
            $memoryCtx,
            $skillCtx,
            $toolCtx,
            $constraints,
        ]));
    }

    /**
     * Build a compact capability catalog for the NL classifier.
     * Returns a plain text block describing every active command + its skill.
     */
    public function buildCapabilityCatalog(): string
    {
        $commands = AllowedCommand::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $skillMap = Skill::where('is_active', true)
            ->get()
            ->keyBy('name');

        $lines = [];
        foreach ($commands as $cmd) {
            $line = "- **{$cmd->name}** ({$cmd->category}): {$cmd->description}";

            if ($cmd->skill_required && isset($skillMap[$cmd->skill_required])) {
                $sk      = $skillMap[$cmd->skill_required];
                $fm      = $sk->parseFrontmatter();
                $envReq  = $fm['env_required'] ?? [];
                if (! empty($envReq)) {
                    $line .= " [requires: " . implode(', ', $envReq) . "]";
                }
            }

            if (! empty($cmd->tools_allowed)) {
                $line .= " | tools: " . implode(', ', $cmd->tools_allowed);
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    // -------------------------------------------------------------------------

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
You are NOT a chatbot — you are an execution engine with intelligence.
Never attempt to access resources outside your granted tools and paths.
Always respond in the same language the user used.
PROMPT;
    }

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
     * Build a rich, detailed tool reference block.
     *
     * Uses the full JSON schemas from AgentOrchestrator combined with
     * the human-readable descriptions stored in the DB.
     *
     * @param  array<string>      $toolNames
     * @param  array<int, array>  $toolDefinitions  [{name, description, input_schema}, ...]
     */
    private function buildToolContext(array $toolNames, array $toolDefinitions): string
    {
        if (empty($toolNames)) {
            return "## Available Tools\n\nNo tools are available for this session.";
        }

        // Fetch full descriptions from DB (richer than the hardcoded one-liners)
        $dbTools = Tool::whereIn('name', $toolNames)->get()->keyBy('name');

        // Index schema definitions by tool name
        $defsByName = [];
        foreach ($toolDefinitions as $def) {
            $defsByName[$def['name']] = $def;
        }

        $count = count($toolNames);
        $lines = [
            "## Available Tools",
            "",
            "You have **{$count} tool(s)** available. Call them precisely — wrong parameter names cause errors.",
            "",
        ];

        foreach ($toolNames as $name) {
            $dbTool = $dbTools[$name] ?? null;
            $def    = $defsByName[$name] ?? null;

            $desc       = $dbTool?->description ?? $def['description'] ?? $name;
            $properties = $def['input_schema']['properties'] ?? [];
            $required   = $def['input_schema']['required']   ?? [];

            $lines[] = "### `{$name}`";
            $lines[] = $desc;

            if (! empty($properties)) {
                $lines[] = "";
                $lines[] = "Parameters:";
                foreach ($properties as $pName => $pDef) {
                    $pType = $pDef['type'] ?? 'string';
                    $pDesc = $pDef['description'] ?? '';
                    $req   = in_array($pName, $required, true) ? ' *(required)*' : ' *(optional)*';
                    $pLine = "- `{$pName}` ({$pType}){$req}";
                    if ($pDesc) $pLine .= " — {$pDesc}";
                    $lines[] = $pLine;
                }
            } else {
                $lines[] = "No parameters required.";
            }

            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    private function buildConstraints(AllowedCommand $command, int $maxToolCalls, int $timeoutSeconds): string
    {
        return <<<CONSTRAINTS
## Session Constraints
- Command: `{$command->name}`
- Maximum tool calls allowed: {$maxToolCalls}
- Session timeout: {$timeoutSeconds} seconds
- Stay within these limits. Stop and return your final answer before exceeding them.
CONSTRAINTS;
    }
}
