<?php

declare(strict_types=1);

namespace App\Services\Generator;

use App\Models\AllowedCommand;
use App\Models\Skill;
use App\Models\Tool;
use App\Services\Llm\LlmRouter;
use Illuminate\Support\Facades\Log;

/**
 * Web-native skill/tool generator.
 *
 * Unlike GeneratorService (Telegram multi-turn), this takes all inputs at once
 * and returns results as an array instead of sending Telegram messages.
 * Generated tool code is automatically inserted into ToolExecutor.php.
 */
class WebGeneratorService
{
    private const MODEL    = 'claude-opus-4-6';
    private const PROVIDER = 'anthropic';

    public function __construct(
        private readonly LlmRouter $llm,
    ) {}

    /**
     * Generate a skill, a tool, or both.
     *
     * @param  string   $type         'skill' | 'tool' | 'skilltool'
     * @param  string   $name         snake_case identifier
     * @param  string   $description  Detailed description of what to build
     * @param  string[] $tools        Tool names to include in skill / grant in command
     * @param  string   $extraPrompt  Optional extra instructions for Claude
     * @return array{
     *   skill?: array{id: int, name: string, path: string, content: string},
     *   tool?:  array{id: int, name: string, code: string},
     *   command?: array{id: int, name: string},
     *   errors: string[],
     * }
     */
    public function generate(
        string $type,
        string $name,
        string $description,
        array $tools = [],
        string $extraPrompt = '',
    ): array {
        $result = ['errors' => []];

        if ($type === 'skill' || $type === 'skilltool') {
            try {
                $result['skill'] = $this->generateSkill($name, $description, $tools, $extraPrompt);
            } catch (\Throwable $e) {
                $result['errors'][] = "Skill generation failed: " . $e->getMessage();
                Log::error('[WebGeneratorService] Skill generation failed.', ['error' => $e->getMessage()]);
            }
        }

        if ($type === 'tool' || $type === 'skilltool') {
            try {
                $result['tool'] = $this->generateTool($name, $description, $extraPrompt);
            } catch (\Throwable $e) {
                $result['errors'][] = "Tool generation failed: " . $e->getMessage();
                Log::error('[WebGeneratorService] Tool generation failed.', ['error' => $e->getMessage()]);
            }
        }

        // Register / update the command that ties everything together
        if (! empty($result['skill']) || ! empty($result['tool'])) {
            try {
                $result['command'] = $this->registerCommand($name, $description, $type, $tools);
            } catch (\Throwable $e) {
                $result['errors'][] = "Command registration failed: " . $e->getMessage();
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------

    private function generateSkill(string $name, string $description, array $tools, string $extraPrompt): array
    {
        $toolsStr  = empty($tools) ? 'none' : implode(', ', $tools);
        $skillsDir = base_path("skills/{$name}");

        $prompt = <<<PROMPT
Generate a complete SKILL.md file for a FlamingDragon agentic AI system skill.

The SKILL.md format is:
---
name: {$name}
display_name: [Human-readable title, Title Case]
version: 1.0.0
description: "[One-line description]"
author: FlamingDragon AI Generator
tools_required: [{$toolsStr}]
---

# [Skill Title]

## Overview
[2-3 sentence explanation of what this skill does and when to use it]

## Instructions

### [Sub-task 1]
[Numbered steps the AI agent should follow]

### [Sub-task 2]
[...]

## Examples

[2-4 concrete examples in the format:]
User: "[Natural language request]"
→ Agent action description

## Notes
[Caveats, limitations, API requirements, error handling tips]

---

Skill to generate:
- Name: {$name}
- Description: {$description}
- Tools available: {$toolsStr}
{$this->extraSection($extraPrompt)}

Generate ONLY the complete SKILL.md content starting with the --- frontmatter. No intro text, no explanation outside the file.
PROMPT;

        $response  = $this->callLlm($prompt);
        $markdown  = $this->stripFences($response);

        if (! is_dir($skillsDir)) {
            mkdir($skillsDir, 0755, true);
        }

        $mdPath = "{$skillsDir}/SKILL.md";
        file_put_contents($mdPath, $markdown);

        $displayName = ucwords(str_replace(['_', '-'], ' ', $name));

        $skill = Skill::updateOrCreate(['name' => $name], [
            'display_name'  => $displayName,
            'description'   => $description,
            'version'       => '1.0.0',
            'skill_path'    => "skills/{$name}",
            'skill_md_path' => $mdPath,
            'is_active'     => true,
            'installed_at'  => now(),
        ]);

        // Parse and store env_required / tools_required from frontmatter
        $frontmatter = $skill->parseFrontmatter();
        $skill->update([
            'env_required'   => $frontmatter['env_required']   ?? null,
            'tools_required' => $frontmatter['tools_required'] ?? null,
        ]);

        return [
            'id'      => $skill->id,
            'name'    => $name,
            'path'    => $mdPath,
            'content' => $markdown,
        ];
    }

    private function generateTool(string $name, string $description, string $extraPrompt): array
    {
        $className  = str_replace(['_', '-'], '', ucwords($name, '_-'));
        $methodName = 'run' . $className;

        $prompt = <<<PROMPT
Generate a private PHP method for the FlamingDragon `ToolExecutor` class.

Requirements:
- Method signature: `private function {$methodName}(array \$args): string`
- It must return a string result (never null)
- Validate required arguments with: `\$arg = \$args['arg'] ?? throw new \\RuntimeException('{$name}: missing "arg"');`
- Throw `\\RuntimeException` with clear messages on errors
- Use `\\Illuminate\\Support\\Facades\\Http` for any HTTP calls (with `->timeout(30)`)
- Use `\\Illuminate\\Support\\Facades\\DB` for any database operations
- Use `env('VAR_NAME')` for credentials, throw RuntimeException if missing
- Use only standard PHP + Laravel — no extra composer packages unless absolutely necessary
- Include all necessary logic; do not add placeholder comments

What the tool must do:
{$description}
{$this->extraSection($extraPrompt)}

Respond with ONLY the PHP method starting from `    private function {$methodName}` through its closing brace.
No markdown fences, no class wrapper, no namespace, no use statements, no explanation.
PROMPT;

        $response   = $this->callLlm($prompt);
        $methodCode = $this->stripFences($response);
        $methodCode = trim($methodCode);

        // Ensure it starts with the correct signature
        if (! str_contains($methodCode, "function {$methodName}")) {
            throw new \RuntimeException("Claude did not return a valid method for {$methodName}.");
        }

        // Insert the method into ToolExecutor.php before the logStep method
        $this->insertToolMethod($methodCode);

        // Add to dispatch table
        $this->insertDispatchEntry($name, $methodName);

        // Register in DB — disabled until reviewed
        $displayName = ucwords(str_replace(['_', '-'], ' ', $name));

        $tool = Tool::updateOrCreate(['name' => $name], [
            'display_name'          => $displayName,
            'description'           => $description,
            'type'                  => 'builtin',
            'risk_level'            => 'moderate',
            'requires_confirmation' => false,
            'is_active'             => false, // disabled until user enables it
        ]);

        return [
            'id'   => $tool->id,
            'name' => $name,
            'code' => $methodCode,
        ];
    }

    private function registerCommand(string $name, string $description, string $type, array $tools): array
    {
        $category    = 'personal';
        $skillName   = ($type === 'skill' || $type === 'skilltool') ? $name : null;
        $toolAllowed = $type === 'tool' ? [$name] : ($type === 'skilltool' ? [$name, ...$tools] : $tools);

        $command = AllowedCommand::updateOrCreate(['name' => $name], [
            'description'    => $description,
            'category'       => $category,
            'execution_mode' => 'sync',
            'timeout_seconds'=> 60,
            'tools_allowed'  => $toolAllowed,
            'is_dangerous'   => false,
            'skill_required' => $skillName,
        ]);

        return ['id' => $command->id, 'name' => $name];
    }

    // -------------------------------------------------------------------------
    // ToolExecutor.php manipulation
    // -------------------------------------------------------------------------

    private function insertToolMethod(string $methodCode): void
    {
        $file    = app_path('Services/Agent/ToolExecutor.php');
        $content = file_get_contents($file);

        // Insert before the logStep sentinel
        $marker = '    // =========================================================================

    private function logStep(';

        if (! str_contains($content, $marker)) {
            throw new \RuntimeException('Could not find insertion point in ToolExecutor.php');
        }

        $indentedCode = "\n    " . str_replace("\n", "\n    ", rtrim($methodCode)) . "\n";
        $newContent   = str_replace($marker, $indentedCode . "\n" . $marker, $content);

        file_put_contents($file, $newContent);
    }

    private function insertDispatchEntry(string $toolName, string $methodName): void
    {
        $file    = app_path('Services/Agent/ToolExecutor.php');
        $content = file_get_contents($file);

        $entry  = "            '{$toolName}' => \$this->{$methodName}(\$args),\n";
        $marker = "            default                 => throw new RuntimeException(\"Unknown tool: {\$toolName}\"),";

        if (str_contains($content, "'{$toolName}'")) {
            return; // already registered
        }

        $newContent = str_replace($marker, $entry . $marker, $content);
        file_put_contents($file, $newContent);
    }

    // -------------------------------------------------------------------------

    private function callLlm(string $prompt): string
    {
        $response = $this->llm->chat(
            [['role' => 'user', 'content' => $prompt]],
            [],
            ['provider' => self::PROVIDER, 'model' => self::MODEL],
        );
        return $response->content;
    }

    private function stripFences(string $text): string
    {
        $text = preg_replace('/^```(?:php|markdown|md)?\s*/m', '', $text);
        $text = preg_replace('/^```\s*$/m', '', $text);
        return trim($text);
    }

    private function extraSection(string $extra): string
    {
        return $extra !== '' ? "\nAdditional instructions:\n{$extra}" : '';
    }
}
