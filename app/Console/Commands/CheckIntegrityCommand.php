<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckIntegrityCommand extends Command
{
    protected $signature = 'fd:check-integrity';

    protected $description = 'Verify referential integrity: every tools_required entry in SKILLS.md must exist as a name in TOOLS.md.';

    public function handle(): int
    {
        $toolsMd  = base_path('TOOLS.md');
        $skillsMd = base_path('SKILLS.md');

        if (! is_file($toolsMd)) {
            $this->error("TOOLS.md not found. Run: php artisan fd:export-registry --target=tools");
            return self::FAILURE;
        }
        if (! is_file($skillsMd)) {
            $this->error("SKILLS.md not found. Run: php artisan fd:export-registry --target=skills");
            return self::FAILURE;
        }

        // Extract all tool names from TOOLS.md
        $toolNames = $this->extractNames($toolsMd);
        $this->info("[fd:check-integrity] TOOLS.md: " . count($toolNames) . " tool found.");

        // Extract skills and their tools_required from SKILLS.md
        $skills = $this->extractSkills($skillsMd);
        $this->info("[fd:check-integrity] SKILLS.md: " . count($skills) . " skill found.");

        $errors  = [];
        $okCount = 0;

        foreach ($skills as $skillName => $toolsRequired) {
            foreach ($toolsRequired as $required) {
                if (in_array($required, $toolNames, true)) {
                    $okCount++;
                } else {
                    $errors[] = "Skill '{$skillName}' requires '{$required}' — NOT FOUND in TOOLS.md";
                }
            }
        }

        if (! empty($errors)) {
            $this->error("[fd:check-integrity] FAILED — " . count($errors) . " integrity error(s):");
            foreach ($errors as $err) {
                $this->error("  ✗ {$err}");
            }
            $this->info("  ✓ {$okCount} tool references OK.");
            return self::FAILURE;
        }

        $this->info("[fd:check-integrity] OK — all {$okCount} tool references are valid.");
        return self::SUCCESS;
    }

    /**
     * Extract all `- name: <value>` lines from a .md file.
     *
     * @return list<string>
     */
    private function extractNames(string $filePath): array
    {
        $content = file_get_contents($filePath);
        preg_match_all('/^- name:\s*(\S+)/m', $content, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Extract skill name → tools_required[] from SKILLS.md.
     * Parses the YAML blocks: looks for `- name: <skill>` followed by
     * `tools_required:` and then `    - <tool>` lines.
     *
     * @return array<string, list<string>>
     */
    private function extractSkills(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $skills  = [];

        // Split on yaml code blocks
        preg_match_all('/```yaml(.*?)```/s', $content, $blocks);

        foreach ($blocks[1] ?? [] as $block) {
            $skillName     = null;
            $toolsRequired = [];
            $inTools       = false;

            foreach (explode("\n", $block) as $line) {
                // Detect skill name
                if (preg_match('/^\s*- name:\s*(\S+)/', $line, $m)) {
                    $skillName = $m[1];
                    $inTools   = false;
                    continue;
                }
                // Enter tools_required section
                if (preg_match('/^\s+tools_required:/', $line)) {
                    $inTools = true;
                    continue;
                }
                // Exit tools_required section on next top-level key
                if ($inTools && preg_match('/^\s{2}[a-z_]+:/', $line) && ! preg_match('/^\s{4}/', $line)) {
                    $inTools = false;
                }
                // Collect tool names
                if ($inTools && preg_match('/^\s+- (\S+)/', $line, $m)) {
                    $toolsRequired[] = $m[1];
                }
            }

            if ($skillName !== null) {
                $skills[$skillName] = $toolsRequired;
            }
        }

        return $skills;
    }
}
