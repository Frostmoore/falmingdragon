<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Skill;
use App\Models\Tool;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uses Claude Opus to modify tool implementations and skill definitions
 * from within the dashboard.
 */
class AIEditorService
{
    private const MODEL    = 'claude-opus-4-6';
    private const API_URL  = 'https://api.anthropic.com/v1/messages';
    private const MAX_TOKENS = 8096;

    // -------------------------------------------------------------------------
    // Tool editing
    // -------------------------------------------------------------------------

    /**
     * Ask Claude Opus to modify a tool's implementation.
     *
     * Returns the new PHP method code as a string (ready to apply).
     * Does NOT write to disk — the caller decides when to apply.
     */
    public function suggestToolModification(Tool $tool, string $instruction): string
    {
        $currentCode = $this->extractToolMethod($tool);
        $methodName  = $tool->executorMethod();

        $prompt = <<<PROMPT
You are modifying a PHP method inside `App\\Services\\Agent\\ToolExecutor`.

The method is `{$methodName}` for the tool named `{$tool->name}`.
Tool description: {$tool->description}

Here is the current implementation:

```php
{$currentCode}
```

The user wants this change:
{$instruction}

Respond with ONLY the complete updated PHP method, starting from `private function {$methodName}` through its closing brace. No extra explanation, no markdown fences, no class wrapper — just the method.
PROMPT;

        $response = $this->callClaude($prompt);

        // Strip any accidental markdown fences
        $response = preg_replace('/^```php\s*/m', '', $response);
        $response = preg_replace('/^```\s*$/m', '', $response);

        return trim($response);
    }

    /**
     * Apply a (possibly user-edited) method body into ToolExecutor.php,
     * replacing the old implementation.
     */
    public function applyToolModification(Tool $tool, string $newMethodCode): void
    {
        $file        = app_path('Services/Agent/ToolExecutor.php');
        $content     = file_get_contents($file);
        $methodName  = $tool->executorMethod();

        $oldCode     = $this->extractToolMethod($tool);
        if (empty($oldCode)) {
            throw new RuntimeException("Method {$methodName} not found in ToolExecutor.php");
        }

        $newContent = str_replace(trim($oldCode), trim($newMethodCode), $content);

        if ($newContent === $content) {
            throw new RuntimeException("Replacement had no effect — method text may have changed. Try again.");
        }

        file_put_contents($file, $newContent);
    }

    /**
     * Extract the source code of a single method from ToolExecutor.php.
     * Returns the full method text including the signature line through closing brace.
     */
    public function extractToolMethod(Tool $tool): string
    {
        $file       = app_path('Services/Agent/ToolExecutor.php');
        $content    = file_get_contents($file);
        $methodName = $tool->executorMethod();

        // Find the start of the method signature line
        $pattern = '/( {4}private function ' . preg_quote($methodName, '/') . '\(.*?\): string\n)/';
        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = $matches[0][1];

        // Walk forward counting braces to find the closing }
        $depth    = 0;
        $inMethod = false;
        $end      = $start;
        $len      = strlen($content);

        for ($i = $start; $i < $len; $i++) {
            $ch = $content[$i];
            if ($ch === '{') { $depth++; $inMethod = true; }
            elseif ($ch === '}') {
                $depth--;
                if ($inMethod && $depth === 0) {
                    $end = $i + 1;
                    break;
                }
            }
        }

        return substr($content, $start, $end - $start);
    }

    // -------------------------------------------------------------------------
    // Skill editing
    // -------------------------------------------------------------------------

    /**
     * Ask Claude Opus to modify a skill's SKILL.md.
     *
     * Returns the full new SKILL.md content as a string.
     */
    public function suggestSkillModification(Skill $skill, string $instruction): string
    {
        $currentContent = $skill->readMarkdown() ?? '';

        $prompt = <<<PROMPT
You are modifying a SKILL.md file for the FlamingDragon agentic AI system.

Skill name: {$skill->name}
Skill description: {$skill->description}

Here is the current SKILL.md content:

---
{$currentContent}
---

The user wants this change:
{$instruction}

Respond with ONLY the complete updated SKILL.md file content, starting from the `---` frontmatter opening through to the last line. No extra explanation outside the file content.
PROMPT;

        return trim($this->callClaude($prompt));
    }

    /**
     * Write new content directly to the skill's SKILL.md file.
     */
    public function applySkillModification(Skill $skill, string $newContent): void
    {
        if (empty($skill->skill_md_path)) {
            throw new RuntimeException("Skill '{$skill->name}' has no skill_md_path set.");
        }

        file_put_contents($skill->skill_md_path, $newContent);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function callClaude(string $userPrompt): string
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post(self::API_URL, [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Claude API error: ' . ($response->json('error.message') ?? $response->body()));
        }

        return $response->json('content.0.text') ?? '';
    }
}
