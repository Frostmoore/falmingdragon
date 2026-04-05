<?php

declare(strict_types=1);

namespace App\Services\Skill;

use App\Services\Llm\LlmRouter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-generates new skills via the Large Language Model on user request.
 */
class SkillGenerator
{
    public function __construct(
        private readonly LlmRouter   $llmRouter,
        private readonly SkillManager $skillManager,
    ) {}

    /**
     * Generate a new skill based on a natural-language description.
     * The generated files are written to the skill workspace for review.
     *
     * @param  string  $description  What the skill should do.
     * @return array{skill_md: string, workspace_path: string}
     */
    public function generate(string $description): array
    {
        $workspace = config('flamingdragon.skills.workspace', storage_path('app/flamingdragon/skill-workspace'));
        $skillName = $this->deriveSkillName($description);
        $skillDir  = $workspace . DIRECTORY_SEPARATOR . $skillName;

        if (! is_dir($skillDir)) {
            mkdir($skillDir, 0755, true);
        }

        $prompt = $this->buildGenerationPrompt($description, $skillName);

        try {
            $response = $this->llmRouter->chat([
                ['role' => 'user', 'content' => $prompt],
            ]);

            $skillMdContent = $this->extractSkillMd($response->content);
            $skillMdPath    = $skillDir . DIRECTORY_SEPARATOR . 'SKILL.md';

            file_put_contents($skillMdPath, $skillMdContent);

            Log::info('[SkillGenerator] Skill generated.', [
                'skill'     => $skillName,
                'workspace' => $skillDir,
            ]);

            return [
                'skill_md'       => $skillMdContent,
                'workspace_path' => $skillDir,
            ];
        } catch (Throwable $e) {
            Log::error('[SkillGenerator] Generation failed.', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Move an approved skill from workspace to the skills directory.
     *
     * @param  string  $workspacePath
     * @return \App\Models\Skill
     */
    public function approve(string $workspacePath): \App\Models\Skill
    {
        return $this->skillManager->install($workspacePath);
    }

    private function deriveSkillName(string $description): string
    {
        // Take first few words, lowercase, hyphenate
        $words = preg_split('/\s+/', strtolower($description));
        return implode('-', array_slice($words ?? [], 0, 3));
    }

    private function buildGenerationPrompt(string $description, string $skillName): string
    {
        return <<<PROMPT
Generate a SKILL.md file for a FlamingDragon skill with the following description:

"{$description}"

The skill name should be: {$skillName}

Return ONLY the SKILL.md content in this exact format:

---
name: {$skillName}
description: "One-line description"
version: "1.0.0"
tools_required: ["bash", "file_write"]
---

# Skill Name

## Overview
What this skill does and when to use it.

## Instructions
Step-by-step agent instructions.

## Scripts
List any scripts if needed.

## Examples
Example invocations.

Return only the SKILL.md content, no extra explanation.
PROMPT;
    }

    private function extractSkillMd(string $llmOutput): string
    {
        // Strip markdown code fences if the LLM wrapped it
        if (preg_match('/```(?:markdown)?\s*\n(.*?)```/s', $llmOutput, $m)) {
            return trim($m[1]);
        }
        return trim($llmOutput);
    }
}
