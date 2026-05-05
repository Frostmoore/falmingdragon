<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AllowedCommand;
use App\Models\Tool;
use Illuminate\Console\Command;

class ExportRegistryCommand extends Command
{
    protected $signature = 'fd:export-registry
        {--target=all : What to export: tools, skills, or all}';

    protected $description = 'Generate TOOLS.md and SKILLS.md from DB + skills/ directory. Do not edit those files by hand.';

    private const TOOL_CATEGORIES = [
        'bash'                    => 'sistema',
        'laravel_artisan'         => 'sistema',
        'process_status'          => 'sistema',
        'file_read'               => 'sistema',
        'file_write'              => 'sistema',
        'file_list'               => 'sistema',
        'file_delete'             => 'sistema',
        'file_search'             => 'sistema',
        'git_operation'           => 'infrastruttura',
        'composer_operation'      => 'infrastruttura',
        'npm_operation'           => 'infrastruttura',
        'http_get'                => 'rete',
        'http_post'               => 'rete',
        'json_api'                => 'rete',
        'web_search'              => 'rete',
        'summarize_url'           => 'rete',
        'memory_read'             => 'dati',
        'memory_write'            => 'dati',
        'db_query'                => 'dati',
        'cron_list'               => 'dati',
        'working_memory_append'   => 'dati',
        'working_memory_read'     => 'dati',
        'telegram_send'           => 'telegram',
        'send_telegram_image'     => 'telegram',
        'send_telegram_voice'     => 'telegram',
        'send_email'              => 'email',
        'gmail_list'              => 'email',
        'gmail_read'              => 'email',
        'gmail_send'              => 'email',
        'gmail_search'            => 'email',
        'gmail_mark_read'         => 'email',
        'gmail_trash'             => 'email',
        'google_calendar_list'    => 'calendario',
        'google_calendar_create'  => 'calendario',
        'google_calendar_delete'  => 'calendario',
        'todo_create'             => 'todo',
        'todo_list'               => 'todo',
        'todo_complete'           => 'todo',
        'todo_delete'             => 'todo',
        'shopping_add'            => 'spesa',
        'shopping_items'          => 'spesa',
        'shopping_bought'         => 'spesa',
        'shopping_clear'          => 'spesa',
        'whatsapp_send'           => 'social',
        'facebook_post'           => 'social',
        'facebook_feed'           => 'social',
        'instagram_post'          => 'social',
        'generate_qr'             => 'documenti',
        'generate_pdf'            => 'documenti',
        'generate_docx'           => 'documenti',
        'generate_xlsx'           => 'documenti',
        'analyze_image'           => 'visione',
        'generate_image'          => 'visione',
        'image_generate'          => 'visione',
        'transcribe_audio'        => 'audio',
        'generate_audio'          => 'audio',
        'skill_read'              => 'skill',
        'weather'                 => 'utility',
    ];

    public function handle(): int
    {
        $target = strtolower((string) $this->option('target'));

        if (! in_array($target, ['tools', 'skills', 'all'], true)) {
            $this->error("--target must be tools, skills, or all.");
            return self::FAILURE;
        }

        if ($target === 'tools' || $target === 'all') {
            $this->exportTools();
        }
        if ($target === 'skills' || $target === 'all') {
            $this->exportSkills();
        }

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // TOOLS.md
    // -------------------------------------------------------------------------

    private function exportTools(): void
    {
        $destPath  = base_path('TOOLS.md');
        $timestamp = now()->toIso8601String();

        $tools = Tool::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Group by category
        $byCategory = [];
        foreach ($tools as $tool) {
            $cat = self::TOOL_CATEGORIES[$tool->name] ?? 'altro';
            $byCategory[$cat][] = $tool;
        }
        ksort($byCategory);

        // Changelog: detect added/removed vs previous file
        $changelogLine = $this->toolsChangelog($destPath, $tools->pluck('name')->toArray(), $timestamp);

        // Build index
        $indexLines = [];
        foreach ($byCategory as $cat => $catTools) {
            $names = implode(', ', array_map(fn ($t) => "`{$t->name}`", $catTools));
            $indexLines[] = "- [{$cat}](#{$cat}) — {$names}";
        }

        // Build registry
        $registryLines = [];
        foreach ($byCategory as $cat => $catTools) {
            $registryLines[] = "### {$cat}";
            $registryLines[] = '';
            foreach ($catTools as $tool) {
                $envRequired = is_array($tool->config_keys) ? $tool->config_keys : [];
                $registryLines[] = "```yaml";
                $registryLines[] = "- name: {$tool->name}";
                $registryLines[] = "  display_name: \"{$tool->display_name}\"";
                $desc = addcslashes((string) $tool->description, '"');
                $registryLines[] = "  description: \"{$desc}\"";
                $registryLines[] = "  category: {$cat}";
                $registryLines[] = "  risk_level: " . ($tool->risk_level->value ?? (string) $tool->risk_level);
                $riskCat = $tool->risk_category instanceof \App\Enums\RiskCategory
                    ? $tool->risk_category->value
                    : ($tool->risk_category ?? 'null');
                $registryLines[] = "  risk_category: {$riskCat}";
                $registryLines[] = "  requires_confirmation: " . ($tool->requires_confirmation ? 'true' : 'false');
                if (empty($envRequired)) {
                    $registryLines[] = "  env_required: []";
                } else {
                    $registryLines[] = "  env_required:";
                    foreach ($envRequired as $env) {
                        $registryLines[] = "    - {$env}";
                    }
                }
                $registryLines[] = "```";
                $registryLines[] = '';
            }
        }

        $content = implode("\n", [
            "> ⚠️ File generato automaticamente da `php artisan fd:export-registry`.",
            "> Non modificare a mano: le modifiche vanno fatte nel DB/seeder, poi riesegui l'export.",
            "> Ultima generazione: {$timestamp}",
            '',
            '# TOOLS.md — Tool Registry',
            '',
            '## Indice per categoria',
            '',
            implode("\n", $indexLines),
            '',
            '## Registry',
            '',
            implode("\n", $registryLines),
            '## Changelog',
            '',
            $changelogLine,
        ]);

        file_put_contents($destPath, $content);
        $this->info("[fd:export-registry] TOOLS.md written — " . count($tools) . " tool.");
    }

    private function toolsChangelog(string $destPath, array $newNames, string $timestamp): string
    {
        $existingChangelog = '';
        $changeEntry       = '';

        if (! is_file($destPath)) {
            $changeEntry = "- [{$timestamp}] Initial export. " . count($newNames) . " tool registrati.";
        } else {
            $old     = file_get_contents($destPath);
            $oldNames = [];
            preg_match_all('/^- name: (\S+)/m', $old, $m);
            $oldNames = $m[1] ?? [];

            $added   = array_diff($newNames, $oldNames);
            $removed = array_diff($oldNames, $newNames);

            $parts = [];
            if (! empty($added)) {
                $parts[] = "Added: " . implode(', ', $added);
            }
            if (! empty($removed)) {
                $parts[] = "Removed: " . implode(', ', $removed);
            }
            if (empty($parts)) {
                $parts[] = "Re-export (no structural changes).";
            }

            $changeEntry = "- [{$timestamp}] " . implode('. ', $parts);

            // Preserve existing changelog entries
            if (preg_match('/^## Changelog\s*\n+(.*)/ms', $old, $cm)) {
                $existingChangelog = trim($cm[1]);
            }
        }

        return $existingChangelog
            ? $changeEntry . "\n" . $existingChangelog
            : $changeEntry;
    }

    // -------------------------------------------------------------------------
    // SKILLS.md
    // -------------------------------------------------------------------------

    private function exportSkills(): void
    {
        $destPath  = base_path('SKILLS.md');
        $timestamp = now()->toIso8601String();

        $skillsDir = base_path('skills');
        if (! is_dir($skillsDir)) {
            $this->error("skills/ directory not found.");
            return;
        }

        $skillDirs = array_filter(scandir($skillsDir), fn ($e) => $e !== '.' && $e !== '..' && is_dir("{$skillsDir}/{$e}"));
        sort($skillDirs);

        // Load commands grouped by skill_required
        $commandsBySkill = AllowedCommand::where('is_active', true)
            ->whereNotNull('skill_required')
            ->get()
            ->groupBy('skill_required');

        $skills        = [];
        $indexLines    = [];
        $registryLines = [];

        foreach ($skillDirs as $skillName) {
            $mdPath = "{$skillsDir}/{$skillName}/SKILL.md";
            if (! is_file($mdPath)) {
                continue;
            }

            $fm = $this->parseFrontmatter(file_get_contents($mdPath));
            if (empty($fm)) {
                continue;
            }

            $toolsRequired = $fm['tools_required'] ?? [];
            $envRequired   = $fm['env_required'] ?? [];
            $description   = $fm['description'] ?? '';
            $displayName   = ucwords(str_replace(['-', '_'], ' ', $skillName));

            $commands = ($commandsBySkill[$skillName] ?? collect())->pluck('name')->toArray();

            $skills[] = $skillName;

            $indexLines[] = "- [{$skillName}](#{$skillName}) — {$description}";

            $registryLines[] = "### {$skillName}";
            $registryLines[] = '';
            $registryLines[] = "```yaml";
            $registryLines[] = "- name: {$skillName}";
            $registryLines[] = "  display_name: \"{$displayName}\"";
            $registryLines[] = "  description: \"" . addcslashes($description, '"') . "\"";

            if (empty($toolsRequired)) {
                $registryLines[] = "  tools_required: []";
            } else {
                $registryLines[] = "  tools_required:";
                foreach ($toolsRequired as $t) {
                    $registryLines[] = "    - {$t}";
                }
            }

            if (empty($envRequired)) {
                $registryLines[] = "  env_required: []";
            } else {
                $registryLines[] = "  env_required:";
                foreach ($envRequired as $e) {
                    $registryLines[] = "    - {$e}";
                }
            }

            if (empty($commands)) {
                $registryLines[] = "  commands: []";
            } else {
                $registryLines[] = "  commands:";
                foreach ($commands as $c) {
                    $registryLines[] = "    - {$c}";
                }
            }

            $registryLines[] = "```";
            $registryLines[] = '';
        }

        $changelogLine = $this->skillsChangelog($destPath, $skills, $timestamp);

        $content = implode("\n", [
            "> ⚠️ File generato automaticamente da `php artisan fd:export-registry`.",
            "> Non modificare a mano: le modifiche vanno fatte nei file skills/*/SKILL.md, poi riesegui l'export.",
            "> Ultima generazione: {$timestamp}",
            '',
            '# SKILLS.md — Skill Registry',
            '',
            '## Indice',
            '',
            implode("\n", $indexLines),
            '',
            '## Registry',
            '',
            implode("\n", $registryLines),
            '## Changelog',
            '',
            $changelogLine,
        ]);

        file_put_contents($destPath, $content);
        $this->info("[fd:export-registry] SKILLS.md written — " . count($skills) . " skill.");
    }

    private function skillsChangelog(string $destPath, array $newNames, string $timestamp): string
    {
        $existingChangelog = '';
        $changeEntry       = '';

        if (! is_file($destPath)) {
            $changeEntry = "- [{$timestamp}] Initial export. " . count($newNames) . " skill registrate.";
        } else {
            $old      = file_get_contents($destPath);
            $oldNames = [];
            preg_match_all('/^- name: (\S+)/m', $old, $m);
            $oldNames = $m[1] ?? [];

            $added   = array_diff($newNames, $oldNames);
            $removed = array_diff($oldNames, $newNames);

            $parts = [];
            if (! empty($added)) {
                $parts[] = "Added: " . implode(', ', $added);
            }
            if (! empty($removed)) {
                $parts[] = "Removed: " . implode(', ', $removed);
            }
            if (empty($parts)) {
                $parts[] = "Re-export (no structural changes).";
            }

            $changeEntry = "- [{$timestamp}] " . implode('. ', $parts);

            if (preg_match('/^## Changelog\s*\n+(.*)/ms', $old, $cm)) {
                $existingChangelog = trim($cm[1]);
            }
        }

        return $existingChangelog
            ? $changeEntry . "\n" . $existingChangelog
            : $changeEntry;
    }

    /**
     * Parse YAML-like frontmatter between --- delimiters.
     * Handles string values and JSON-array values (as used in SKILL.md files).
     *
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
            return [];
        }

        $result = [];
        foreach (explode("\n", trim($m[1])) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $val] = explode(':', $line, 2);
            $key = trim($key);
            $val = trim($val);

            // JSON array value
            if (str_starts_with($val, '[')) {
                $decoded = json_decode($val, true);
                $result[$key] = is_array($decoded) ? $decoded : [];
                continue;
            }

            // Quoted string
            if (preg_match('/^"(.*)"$/', $val, $q)) {
                $result[$key] = $q[1];
                continue;
            }

            $result[$key] = $val;
        }

        return $result;
    }
}
