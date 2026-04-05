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
        'is_active',
        'installed_at',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'has_scripts'  => 'boolean',
        'is_active'    => 'boolean',
        'installed_at' => 'datetime',
    ];

    /** Read and return the contents of the SKILL.md file. */
    public function readMarkdown(): ?string
    {
        if (file_exists($this->skill_md_path)) {
            return file_get_contents($this->skill_md_path) ?: null;
        }
        return null;
    }
}
