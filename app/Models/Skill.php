<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'version',
        'skill_path',
        'skill_md_path',
        'has_scripts',
        'dependencies',
        'env_required',
        'tools_required',
        'is_active',
        'installed_at',
    ];

    protected $casts = [
        'dependencies'   => 'array',
        'env_required'   => 'array',
        'tools_required' => 'array',
        'has_scripts'    => 'boolean',
        'is_active'      => 'boolean',
        'installed_at'   => 'datetime',
    ];

    /** Read and return the contents of the SKILL.md file. */
    public function readMarkdown(): ?string
    {
        if (file_exists($this->skill_md_path)) {
            return file_get_contents($this->skill_md_path) ?: null;
        }
        return null;
    }

    /**
     * Parse YAML-like frontmatter from SKILL.md and return it as an array.
     * Supports simple scalar values and JSON-style arrays.
     *
     * @return array<string, mixed>
     */
    public function parseFrontmatter(): array
    {
        $content = $this->readMarkdown();
        if (! $content || ! str_starts_with($content, '---')) {
            return [];
        }

        $end = strpos($content, '---', 3);
        if ($end === false) {
            return [];
        }

        $yaml   = substr($content, 3, $end - 3);
        $result = [];

        foreach (explode("\n", $yaml) as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // JSON array like ["a","b"]
            if (str_starts_with($value, '[')) {
                $decoded = json_decode($value, true);
                $result[$key] = is_array($decoded) ? $decoded : [];
            } else {
                // Strip surrounding quotes
                $result[$key] = trim($value, '"\'');
            }
        }

        return $result;
    }

    /** Get env_required from DB or fall back to SKILL.md frontmatter. */
    public function getEnvRequiredList(): array
    {
        if (! empty($this->env_required)) {
            return $this->env_required;
        }
        return $this->parseFrontmatter()['env_required'] ?? [];
    }

    /** Get tools_required from DB or fall back to SKILL.md frontmatter. */
    public function getToolsRequiredList(): array
    {
        if (! empty($this->tools_required)) {
            return $this->tools_required;
        }
        return $this->parseFrontmatter()['tools_required'] ?? [];
    }
}
