<?php

declare(strict_types=1);

namespace App\Services\Generator;

use App\Models\AllowedCommand;
use App\Models\Skill;
use App\Models\Tool;
use App\Services\Llm\LlmRouter;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles multi-turn interactive generation of Skills and Tools via Telegram.
 *
 * State machine stored in Cache under "fd_gen:{chatId}".
 * State structure:
 * {
 *   type: 'skill'|'tool'|'skilltool',
 *   step: 'ask_name'|'ask_description'|'ask_tools'|'ask_confirm'|'generating',
 *   name: string,
 *   description: string,
 *   tools: string[],
 * }
 */
class GeneratorService
{
    private const CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private readonly TelegramService $telegram,
        private readonly LlmRouter       $llmRouter,
    ) {}

    /** Returns true if there is an active generator conversation for this chat. */
    public function isActive(int $chatId): bool
    {
        return Cache::has("fd_gen:{$chatId}");
    }

    /** Start a new generator conversation. */
    public function start(int $chatId, string $type): void
    {
        Cache::put("fd_gen:{$chatId}", [
            'type' => $type,
            'step' => 'ask_name',
        ], self::CACHE_TTL);

        $typeName = match($type) {
            'tool'      => 'tool',
            'skilltool' => 'skill + tool pair',
            default     => 'skill',
        };

        $this->telegram->sendMessage($chatId,
            "🛠 <b>Generator started — {$typeName}</b>\n\n"
            . "Let's gather the information needed.\n\n"
            . "<b>Step 1/3 — Name</b>\n"
            . "What should this {$typeName} be called? (use_snake_case, no spaces)"
        );
    }

    /** Handle the next message from the user inside an active conversation. */
    public function handleMessage(int $chatId, string $text): void
    {
        $state = Cache::get("fd_gen:{$chatId}");
        if ($state === null) return;

        if (strtolower(trim($text)) === '/cancel') {
            Cache::forget("fd_gen:{$chatId}");
            $this->telegram->sendMessage($chatId, '❌ Generator cancelled.');
            return;
        }

        try {
            match($state['step']) {
                'ask_name'        => $this->handleName($chatId, $state, $text),
                'ask_description' => $this->handleDescription($chatId, $state, $text),
                'ask_tools'       => $this->handleTools($chatId, $state, $text),
                'ask_confirm'     => $this->handleConfirm($chatId, $state, $text),
                default           => $this->telegram->sendMessage($chatId, '⏳ Generation in progress, please wait…'),
            };
        } catch (Throwable $e) {
            Cache::forget("fd_gen:{$chatId}");
            Log::error('[GeneratorService] Error in conversation.', ['error' => $e->getMessage()]);
            $this->telegram->sendMessage($chatId, '❌ Generator error: ' . $e->getMessage());
        }
    }

    private function handleName(int $chatId, array $state, string $text): void
    {
        $name = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($text)));
        if (strlen($name) < 2) {
            $this->telegram->sendMessage($chatId, '⚠ Name too short. Try again.');
            return;
        }

        $state['name'] = $name;
        $state['step'] = 'ask_description';
        Cache::put("fd_gen:{$chatId}", $state, self::CACHE_TTL);

        $this->telegram->sendMessage($chatId,
            "✓ Name: <code>{$name}</code>\n\n"
            . "<b>Step 2/3 — Description</b>\n"
            . "Describe what this " . $state['type'] . " should do. Be as detailed as possible — "
            . "the more context you give, the better the generated code will be."
        );
    }

    private function handleDescription(int $chatId, array $state, string $text): void
    {
        if (strlen(trim($text)) < 10) {
            $this->telegram->sendMessage($chatId, '⚠ Description too short. Please provide more detail.');
            return;
        }

        $state['description'] = trim($text);
        $state['step']        = 'ask_tools';
        Cache::put("fd_gen:{$chatId}", $state, self::CACHE_TTL);

        $availableTools = implode(', ', [
            'bash', 'file_read', 'file_write', 'file_list',
            'http_get', 'http_post', 'memory_read', 'memory_write',
            'telegram_send', 'process_status', 'laravel_artisan',
            'git_operation', 'composer_operation', 'npm_operation',
            'web_search', 'json_api', 'send_email',
        ]);

        $this->telegram->sendMessage($chatId,
            "✓ Description saved.\n\n"
            . "<b>Step 3/3 — Tools</b>\n"
            . "Which tools should be available?\n\n"
            . "Available: <code>{$availableTools}</code>\n\n"
            . "Reply with a comma-separated list, or <code>none</code> if no tools are needed."
        );
    }

    private function handleTools(int $chatId, array $state, string $text): void
    {
        $tools = strtolower(trim($text)) === 'none'
            ? []
            : array_map('trim', explode(',', $text));

        $state['tools'] = $tools;
        $state['step']  = 'ask_confirm';
        Cache::put("fd_gen:{$chatId}", $state, self::CACHE_TTL);

        $toolsStr = empty($tools) ? 'none' : implode(', ', $tools);
        $type     = $state['type'];

        $this->telegram->sendMessage($chatId,
            "✓ Tools: <code>{$toolsStr}</code>\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "📋 <b>Summary</b>\n"
            . "Type: <code>{$type}</code>\n"
            . "Name: <code>{$state['name']}</code>\n"
            . "Tools: <code>{$toolsStr}</code>\n"
            . "Description: {$state['description']}\n\n"
            . "━━━━━━━━━━━━━━━━━━━━\n"
            . "Reply <b>yes</b> to generate with Claude Opus, or <b>no</b> to cancel."
        );
    }

    private function handleConfirm(int $chatId, array $state, string $text): void
    {
        if (! in_array(strtolower(trim($text)), ['yes', 'y', 'si', 'sì', 'ok'], true)) {
            Cache::forget("fd_gen:{$chatId}");
            $this->telegram->sendMessage($chatId, '❌ Generation cancelled.');
            return;
        }

        $state['step'] = 'generating';
        Cache::put("fd_gen:{$chatId}", $state, self::CACHE_TTL);

        $this->telegram->sendMessage($chatId, '⏳ <b>Generating with Claude Opus…</b> This may take 20–60 seconds.');

        $this->generate($chatId, $state);
    }

    private function generate(int $chatId, array $state): void
    {
        $type        = $state['type'];
        $name        = $state['name'];
        $description = $state['description'];
        $tools       = $state['tools'] ?? [];
        $toolsStr    = empty($tools) ? 'none' : implode(', ', $tools);

        try {
            if ($type === 'skill' || $type === 'skilltool') {
                $this->generateSkill($chatId, $name, $description, $tools);
            }

            if ($type === 'tool' || $type === 'skilltool') {
                $this->generateTool($chatId, $name, $description);
            }
        } catch (Throwable $e) {
            Log::error('[GeneratorService] Generation failed.', ['error' => $e->getMessage()]);
            $this->telegram->sendMessage($chatId, '❌ Generation failed: ' . $e->getMessage());
        } finally {
            Cache::forget("fd_gen:{$chatId}");
        }
    }

    private function generateSkill(int $chatId, string $name, string $description, array $tools): void
    {
        $toolsStr  = empty($tools) ? 'none' : implode(', ', $tools);
        $skillsDir = base_path("skills/{$name}");

        $prompt = <<<PROMPT
Generate a complete SKILL.md file for a FlamingDragon skill.

SKILL.md format:
---
name: {$name}
display_name: [Human-readable title]
version: 1.0.0
description: [One-line description]
author: FlamingDragon Generator
tools_required: [{$toolsStr}]
---

# [Skill Title]

## Purpose
[Detailed explanation]

## Instructions
[Step-by-step instructions for the AI agent]

## Examples
[2-3 concrete usage examples]

## Notes
[Any important caveats or limitations]

---

Skill details:
- Name: {$name}
- Description: {$description}
- Available tools: {$toolsStr}

Generate ONLY the SKILL.md content, nothing else. Start with the --- frontmatter block.
PROMPT;

        $response = $this->llmRouter->chat(
            [['role' => 'user', 'content' => $prompt]],
            [],
            ['provider' => 'anthropic', 'model' => 'claude-opus-4-6'],
        );

        $markdown = trim($response->content);

        if (! is_dir($skillsDir)) {
            mkdir($skillsDir, 0755, true);
        }

        file_put_contents("{$skillsDir}/SKILL.md", $markdown);

        // Register in DB
        Skill::updateOrCreate(['name' => $name], [
            'display_name'  => ucwords(str_replace('_', ' ', $name)),
            'description'   => $description,
            'skill_path'    => "skills/{$name}",
            'skill_md_path' => "{$skillsDir}/SKILL.md",
            'is_active'     => true,
            'installed_at'  => now(),
        ]);

        $this->telegram->sendMessage($chatId,
            "✅ <b>Skill <code>{$name}</code> generated!</b>\n\n"
            . "📁 Saved to <code>skills/{$name}/SKILL.md</code>\n"
            . "You can edit it from the dashboard → Skills."
        );
    }

    private function generateTool(int $chatId, string $name, string $description): void
    {
        $className = str_replace('_', '', ucwords($name, '_'));

        $prompt = <<<PROMPT
Generate a PHP method for the FlamingDragon ToolExecutor class.

The method should:
- Be named `run{$className}(array \$args): string`
- Implement: {$description}
- Return a string result
- Throw RuntimeException on error with a clear message
- Be safe and validate required arguments
- Use only standard PHP functions and Laravel's Http facade for HTTP calls

Generate ONLY the PHP method code (no class declaration, no namespace, no use statements).
Start directly with `private function run{$className}`.
PROMPT;

        $response = $this->llmRouter->chat(
            [['role' => 'user', 'content' => $prompt]],
            [],
            ['provider' => 'anthropic', 'model' => 'claude-opus-4-6'],
        );

        $methodCode = trim($response->content);
        // Strip markdown code fences if present
        $methodCode = preg_replace('/^```php?\s*/m', '', $methodCode);
        $methodCode = preg_replace('/```\s*$/m', '', $methodCode);

        // Register in DB — disabled until reviewed and enabled from dashboard
        Tool::updateOrCreate(['name' => $name], [
            'display_name' => ucwords(str_replace('_', ' ', $name)),
            'description'  => $description,
            'type'         => 'builtin',
            'risk_level'   => 'moderate',
            'is_active'    => false,
        ]);

        $this->telegram->sendMessage($chatId,
            "✅ <b>Tool <code>{$name}</code> generated!</b>\n\n"
            . "⚠️ The tool was registered in DB but is <b>disabled</b> pending review.\n"
            . "Add the following method to <code>ToolExecutor.php</code> and enable the tool in the dashboard:\n\n"
            . "<pre>" . htmlspecialchars(substr($methodCode, 0, 2000)) . "</pre>"
        );
    }
}
