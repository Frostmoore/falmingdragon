<?php

declare(strict_types=1);

namespace App\Services\Skill;

use App\Models\Skill;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Manages skill installation, uninstallation, and lifecycle.
 */
class SkillManager
{
    public function __construct(
        private readonly SkillParser $parser,
    ) {}

    /**
     * Install a skill from a source directory.
     *
     * @param  string  $sourcePath  Absolute path to the skill directory.
     * @return Skill
     * @throws RuntimeException
     */
    public function install(string $sourcePath): Skill
    {
        $skillMdPath = $sourcePath . DIRECTORY_SEPARATOR . 'SKILL.md';

        if (! file_exists($skillMdPath)) {
            throw new RuntimeException("SKILL.md not found in {$sourcePath}");
        }

        $meta         = $this->parser->parse($skillMdPath);
        $skillsDir    = config('flamingdragon.skills.directory', base_path('skills'));
        $skillName    = $meta['name'] ?: basename($sourcePath);
        $destPath     = $skillsDir . DIRECTORY_SEPARATOR . $skillName;

        // Copy to skills directory if source is different
        if (realpath($sourcePath) !== realpath($destPath)) {
            $this->copyDirectory($sourcePath, $destPath);
        }

        $hasScripts = is_dir($destPath . DIRECTORY_SEPARATOR . 'scripts');

        $skill = Skill::updateOrCreate(
            ['name' => $skillName],
            [
                'display_name'  => ucwords(str_replace(['-', '_'], ' ', $skillName)),
                'description'   => $meta['description'],
                'version'       => $meta['version'],
                'skill_path'    => $destPath,
                'skill_md_path' => $destPath . DIRECTORY_SEPARATOR . 'SKILL.md',
                'has_scripts'   => $hasScripts,
                'dependencies'  => $meta['tools_required'],
                'is_active'     => true,
                'installed_at'  => now(),
            ]
        );

        Log::info('[SkillManager] Skill installed.', ['skill' => $skillName]);

        return $skill;
    }

    /**
     * Deactivate (soft-uninstall) a skill.
     *
     * @param  int  $skillId
     */
    public function uninstall(int $skillId): void
    {
        $skill = Skill::findOrFail($skillId);
        $skill->update(['is_active' => false]);
        Log::info('[SkillManager] Skill deactivated.', ['skill' => $skill->name]);
    }

    /**
     * Toggle the active state of a skill.
     *
     * @param  int  $skillId
     * @return bool  New active state.
     */
    public function toggle(int $skillId): bool
    {
        $skill = Skill::findOrFail($skillId);
        $newState = ! $skill->is_active;
        $skill->update(['is_active' => $newState]);
        return $newState;
    }

    /**
     * Retrieve an active skill by name.
     *
     * @param  string  $name
     * @return Skill|null
     */
    public function findByName(string $name): ?Skill
    {
        return Skill::where('name', $name)->where('is_active', true)->first();
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $src, string $dst): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $item;
            is_dir($srcPath)
                ? $this->copyDirectory($srcPath, $dstPath)
                : copy($srcPath, $dstPath);
        }
    }
}
